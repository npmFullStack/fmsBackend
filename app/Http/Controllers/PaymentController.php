<?php
// [file name]: PaymentController.php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Models\Booking;
use App\Models\AccountsReceivable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PaymentController extends Controller
{
    private $paymongoSecret;
    private $paymongoUrl;

    public function __construct()
    {
        $this->paymongoSecret = config('services.paymongo.secret_key');
        $this->paymongoUrl = config('services.paymongo.url', 'https://api.paymongo.com/v1');
    }

    public function index(Request $request)
    {
        $perPage = $request->get('per_page', 10);
        $search = $request->get('search', '');
        $status = $request->get('status', 'all');

        $query = Payment::with(['booking', 'user'])
            ->notDeleted();

        // Filter by status
        if ($status !== 'all') {
            $query->where('status', $status);
        }

        // Search
        if (!empty($search)) {
            $query->where(function($q) use ($search) {
                $q->where('reference_number', 'like', '%' . $search . '%')
                  ->orWhere('gcash_mobile_number', 'like', '%' . $search . '%')
                  ->orWhere('paymongo_payment_intent_id', 'like', '%' . $search . '%')
                  ->orWhereHas('booking', function($q) use ($search) {
                      $q->where('booking_number', 'like', '%' . $search . '%');
                  })
                  ->orWhereHas('user', function($q) use ($search) {
                      $q->where('first_name', 'like', '%' . $search . '%')
                        ->orWhere('last_name', 'like', '%' . $search . '%');
                  });
            });
        }

        $sort = $request->get('sort', 'id');
        $direction = $request->get('direction', 'desc');

        $data = $query->orderBy($sort, $direction)->paginate($perPage);

        return response()->json($data);
    }

   public function store(Request $request)
{
    DB::beginTransaction();

    try {
        Log::info('💰 PAYMENT CREATION STARTED');
        Log::info('💰 Request Data:', $request->all());

        $validated = $request->validate([
            'booking_id' => 'required|exists:bookings,id',
            'payment_method' => 'required|in:gcash,paymongo',
            'amount' => 'required|numeric|min:1',
            'gcash_mobile_number' => 'required_if:payment_method,gcash|string|max:20',
        ]);

        Log::info('💰 Validated Data:', $validated);

        // Get authenticated user
        $user = auth()->user();
        Log::info('💰 User ID: ' . $user->id);

        $booking = Booking::notDeleted()->find($validated['booking_id']);

        if (!$booking) {
            Log::error('💰 Booking not found: ' . $validated['booking_id']);
            return response()->json(['message' => 'Booking not found'], 404);
        }

        Log::info('💰 Booking found: ' . $booking->booking_number);

        // Check if user owns the booking
        if ($booking->user_id !== $user->id) {
            Log::error('💰 Unauthorized access - User ' . $user->id . ' trying to access booking ' . $booking->id . ' owned by ' . $booking->user_id);
            return response()->json(['message' => 'Unauthorized access to this booking'], 403);
        }

        // Check if booking has AR record
        $ar = AccountsReceivable::where('booking_id', $booking->id)->first();
        if (!$ar) {
            Log::error('💰 No AR record found for booking: ' . $booking->id);
            return response()->json(['message' => 'No accounts receivable record found for this booking'], 404);
        }

        Log::info('💰 AR Record - Total: ' . $ar->total_payment . ', Collectible: ' . $ar->collectible_amount);

        // Check if amount is valid
        $paymentAmount = $validated['amount'];
        if ($paymentAmount > $ar->collectible_amount) {
            Log::error('💰 Payment amount exceeds collectible amount: ' . $paymentAmount . ' > ' . $ar->collectible_amount);
            return response()->json([
                'message' => 'Payment amount exceeds collectible amount',
                'collectible_amount' => $ar->collectible_amount
            ], 400);
        }

        // Create payment record
        $payment = Payment::create([
            'booking_id' => $validated['booking_id'],
            'user_id' => $user->id,
            'payment_method' => $validated['payment_method'],
            'amount' => $paymentAmount,
            'status' => 'pending',
            'gcash_mobile_number' => $validated['gcash_mobile_number'] ?? null,
            'payment_date' => now(),
        ]);

        Log::info('💰 Payment record created - ID: ' . $payment->id . ', Method: ' . $payment->payment_method . ', Amount: ' . $payment->amount);

        $responseData = [
            'message' => 'Payment created successfully',
            'payment' => $payment->load(['booking', 'user'])
        ];

    
        // In your store method, replace the PayMongo section:
if ($validated['payment_method'] === 'paymongo') {
    Log::info('🔄 Creating PayMongo payment for payment ID: ' . $payment->id);
    try {
        // Try payment link first (guarantees checkout URL)
        $paymentLink = $this->createPaymongoPaymentLink($payment);
        
        if ($paymentLink) {
            Log::info('✅ PayMongo payment link created successfully');
            Log::info('✅ Checkout URL: ' . $paymentLink['attributes']['checkout_url']);
            
            $payment->update([
                'paymongo_payment_intent_id' => $paymentLink['id'],
                'paymongo_checkout_url' => $paymentLink['attributes']['checkout_url'],
                'status' => 'processing'
            ]);
            
            Log::info('✅ Payment record updated with PayMongo details');
            
            // Add PayMongo specific data to response
            $responseData['client_key'] = $paymentLink['attributes']['client_key'];
            $responseData['payment_intent_id'] = $paymentLink['attributes']['payment_intent_id'];
            $responseData['checkout_url'] = $paymentLink['attributes']['checkout_url'];
        }
    } catch (\Exception $e) {
        Log::error('❌ PayMongo payment link failed, trying payment intent: ' . $e->getMessage());
        
        // Fallback to payment intent
        $paymentIntent = $this->createPaymongoPaymentIntent($payment);
        
        if ($paymentIntent) {
            Log::info('✅ PayMongo payment intent created as fallback');
            $payment->update([
                'paymongo_payment_intent_id' => $paymentIntent['id'],
                'paymongo_checkout_url' => $paymentIntent['attributes']['checkout_url'] ?? null,
                'status' => 'processing'
            ]);
            
            $responseData['client_key'] = $paymentIntent['attributes']['client_key'];
            $responseData['payment_intent_id'] = $paymentIntent['id'];
            $responseData['checkout_url'] = $paymentIntent['attributes']['checkout_url'] ?? null;
        }
    }
} else {
            Log::info('💰 GCash payment - no PayMongo intent needed');
        }

        DB::commit();
        Log::info('💰 PAYMENT CREATION COMPLETED SUCCESSFULLY - Payment ID: ' . $payment->id);

        return response()->json($responseData, 201);

    } catch (\Exception $e) {
        DB::rollBack();
        Log::error('💥 PAYMENT CREATION FAILED: ' . $e->getMessage());
        Log::error('💥 Exception: ' . $e->getFile() . ':' . $e->getLine());
        Log::error('💥 Trace: ' . $e->getTraceAsString());
        return response()->json([
            'message' => 'Failed to create payment',
            'error' => $e->getMessage()
        ], 500);
    }
}

private function createPaymongoPaymentLink(Payment $payment)
{
    try {
        Log::info('🔍 Creating PayMongo Payment Link');
        
        $payload = [
            'data' => [
                'attributes' => [
                    'amount' => (int)($payment->amount * 100),
                    'description' => 'Payment for Booking #' . $payment->booking->booking_number,
                    'remarks' => 'Shipping booking payment - ' . $payment->booking->booking_number,
                ]
            ]
        ];

        Log::info('🔍 PayMongo Payment Link Payload:', $payload);

        $response = Http::withHeaders([
            'Authorization' => 'Basic ' . base64_encode($this->paymongoSecret . ':'),
            'Content-Type' => 'application/json',
        ])
        ->timeout(30)
        ->post($this->paymongoUrl . '/links', $payload);

        Log::info('🔍 PayMongo Payment Link Response Status: ' . $response->status());
        Log::info('🔍 PayMongo Payment Link Response Body: ' . $response->body());

        if ($response->successful()) {
            $data = $response->json();
            $paymentLink = $data['data'];
            
            Log::info('✅ Payment Link Created: ' . $paymentLink['id']);
            Log::info('✅ Checkout URL: ' . $paymentLink['attributes']['checkout_url']);
            
            // Return in the same format as payment intent for consistency
            return [
                'id' => $paymentLink['id'],
                'attributes' => [
                    'checkout_url' => $paymentLink['attributes']['checkout_url'],
                    'client_key' => $paymentLink['id'], // Use ID as fallback
                    'payment_intent_id' => $paymentLink['id'], // Use ID as fallback
                    'amount' => $paymentLink['attributes']['amount'],
                    'description' => $paymentLink['attributes']['description']
                ]
            ];
        } else {
            Log::error('❌ PayMongo Payment Link Error: ' . $response->body());
            throw new \Exception('Failed to create payment link');
        }

    } catch (\Exception $e) {
        Log::error('💥 Payment Link Creation Failed: ' . $e->getMessage());
        throw $e;
    }
}

    public function show($id)
    {
        $payment = Payment::with(['booking', 'user'])
            ->notDeleted()
            ->find($id);

        if (!$payment) {
            return response()->json(['message' => 'Payment not found'], 404);
        }

        return response()->json($payment);
    }

    public function update(Request $request, $id)
    {
        $payment = Payment::notDeleted()->find($id);

        if (!$payment) {
            return response()->json(['message' => 'Payment not found'], 404);
        }

        $validated = $request->validate([
            'status' => 'sometimes|in:pending,processing,completed,failed,cancelled',
            'gcash_receipt' => 'nullable|string|max:255',
            'gcash_transaction_id' => 'nullable|string|max:255',
            'paymongo_status' => 'nullable|string|max:255',
        ]);

        $payment->update($validated);

        return response()->json($payment);
    }

    public function destroy($id)
    {
        $payment = Payment::notDeleted()->find($id);

        if (!$payment) {
            return response()->json(['message' => 'Payment not found'], 404);
        }

        $payment->delete();

        return response()->json(['message' => 'Payment deleted successfully'], 200);
    }

    public function getByBooking($bookingId)
    {
        $payments = Payment::with(['user'])
            ->where('booking_id', $bookingId)
            ->notDeleted()
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($payments);
    }

    // Process GCash payment (for admin)
    public function processGCashPayment(Request $request, $id)
    {
        DB::beginTransaction();

        try {
            $validated = $request->validate([
                'status' => 'required|in:completed,failed',
                'gcash_receipt' => 'required_if:status,completed|string|max:255',
                'gcash_transaction_id' => 'required_if:status,completed|string|max:255',
            ]);

            $payment = Payment::notDeleted()->find($id);

            if (!$payment) {
                return response()->json(['message' => 'Payment not found'], 404);
            }

            $payment->update($validated);

            // If payment completed, update AR
            if ($validated['status'] === 'completed') {
                $this->updateAccountsReceivable($payment);
            }

            DB::commit();

            return response()->json([
                'message' => 'Payment processed successfully',
                'payment' => $payment
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to process payment',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // Create payment for customer
    public function createPayment(Request $request, $bookingId)
    {
        $user = auth()->user();
        $booking = Booking::notDeleted()
            ->where('id', $bookingId)
            ->where('user_id', $user->id)
            ->first();

        if (!$booking) {
            return response()->json(['message' => 'Booking not found or unauthorized'], 404);
        }

        return $this->store($request);
    }

    // Check payment status
    public function checkPaymentStatus($id)
    {
        $payment = Payment::notDeleted()->find($id);

        if (!$payment) {
            return response()->json(['message' => 'Payment not found'], 404);
        }

        // If PayMongo payment, check status with PayMongo
        if ($payment->payment_method === 'paymongo' && $payment->paymongo_payment_intent_id) {
            $this->checkPaymongoPaymentStatus($payment);
        }

        return response()->json($payment->fresh());
    }

    private function checkPaymongoPaymentStatus(Payment $payment)
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Basic ' . base64_encode($this->paymongoSecret . ':'),
            ])->get($this->paymongoUrl . '/payment_intents/' . $payment->paymongo_payment_intent_id);

            if ($response->successful()) {
                $data = $response->json();
                $paymentIntent = $data['data'];
                $status = $paymentIntent['attributes']['status'];

                // Update payment status based on PayMongo status
                $paymentStatus = $this->mapPaymongoStatus($status);
                
                $payment->update([
                    'paymongo_status' => $status,
                    'status' => $paymentStatus,
                    'paymongo_response' => $paymentIntent
                ]);

                // If payment is completed, update AR
                if ($paymentStatus === 'completed') {
                    $this->updateAccountsReceivable($payment);
                }
            }
        } catch (\Exception $e) {
            Log::error('Paymongo status check failed: ' . $e->getMessage());
        }
    }

    private function mapPaymongoStatus($paymongoStatus)
    {
        $statusMap = [
            'awaiting_payment_method' => 'pending',
            'awaiting_next_action' => 'processing',
            'processing' => 'processing',
            'succeeded' => 'completed',
            'failed' => 'failed',
            'canceled' => 'cancelled'
        ];

        return $statusMap[$paymongoStatus] ?? 'pending';
    }

    private function updateAccountsReceivable(Payment $payment)
    {
        $ar = AccountsReceivable::where('booking_id', $payment->booking_id)->first();
        if ($ar) {
            $remainingAmount = $ar->collectible_amount - $payment->amount;
            
            $ar->update([
                'collectible_amount' => max(0, $remainingAmount),
                'is_paid' => $remainingAmount <= 0
            ]);

            // Recalculate financials
            $ar->calculateFinancials()->save();
        }
    }
}