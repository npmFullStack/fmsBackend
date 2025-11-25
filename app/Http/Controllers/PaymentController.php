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
                  ->orWhere('provider_payment_id', 'like', '%' . $search . '%')
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
                'payment_method' => 'required|in:gcash,paymongo,bank_transfer',
                'amount' => 'required|numeric|min:1', // Allow amounts as low as 1 peso
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

            // Check if amount is valid - ENFORCE FULL PAYMENT
            $paymentAmount = $validated['amount'];
            if ($paymentAmount != $ar->collectible_amount) {
                Log::error('💰 Payment amount must be full amount: ' . $paymentAmount . ' != ' . $ar->collectible_amount);
                return response()->json([
                    'message' => 'Full payment required. Please pay the complete amount of ' . $ar->collectible_amount,
                    'required_amount' => $ar->collectible_amount,
                    'submitted_amount' => $paymentAmount
                ], 400);
            }

            // Create payment record with new schema
            $payment = Payment::create([
                'booking_id' => $validated['booking_id'],
                'user_id' => $user->id,
                'payment_method' => $validated['payment_method'],
                'amount' => $paymentAmount,
                'status' => 'pending',
                'payment_date' => now(),
                'checkout_created_at' => now(),
                'description' => 'Payment for Booking #' . $booking->booking_number,
                'metadata' => [
                    'booking_number' => $booking->booking_number,
                    'route' => $booking->origin->name . ' → ' . $booking->destination->name,
                    'container_info' => $booking->container_quantity . ' x ' . $booking->containerSize->size
                ]
            ]);

            Log::info('💰 Payment record created - ID: ' . $payment->id . ', Method: ' . $payment->payment_method . ', Amount: ' . $payment->amount);

            $responseData = [
                'message' => 'Payment created successfully',
                'payment' => $payment->load(['booking', 'user']),
                'checkout_url' => null
            ];

            // Handle different payment methods
            if ($validated['payment_method'] === 'paymongo') {
                Log::info('🔄 Creating PayMongo payment for payment ID: ' . $payment->id);
                try {
                    $paymentLink = $this->createPaymongoPaymentLink($payment);
                    
                    if ($paymentLink && isset($paymentLink['attributes']['checkout_url'])) {
                        Log::info('✅ PayMongo payment link created successfully');
                        Log::info('✅ Checkout URL: ' . $paymentLink['attributes']['checkout_url']);
                        
                        $payment->update([
                            'provider_payment_id' => $paymentLink['id'],
                            'provider_checkout_url' => $paymentLink['attributes']['checkout_url'],
                            'provider_response' => $paymentLink,
                            'status' => 'processing'
                        ]);
                        
                        $responseData['checkout_url'] = $paymentLink['attributes']['checkout_url'];
                        $responseData['message'] = 'PayMongo checkout created successfully';
                    }
                } catch (\Exception $e) {
                    Log::error('❌ PayMongo payment link failed: ' . $e->getMessage());
                    // Don't fail the payment creation, just log the error
                }
            } 
            elseif ($validated['payment_method'] === 'gcash') {
                Log::info('🔄 Creating GCash payment for payment ID: ' . $payment->id);
                try {
                    $gcashPayment = $this->createGCashPayment($payment);
                    
                    if ($gcashPayment && isset($gcashPayment['attributes']['checkout_url'])) {
                        Log::info('✅ GCash payment created successfully');
                        Log::info('✅ Checkout URL: ' . $gcashPayment['attributes']['checkout_url']);
                        
                        $payment->update([
                            'provider_payment_id' => $gcashPayment['id'],
                            'provider_checkout_url' => $gcashPayment['attributes']['checkout_url'],
                            'provider_response' => $gcashPayment,
                            'status' => 'processing'
                        ]);
                        
                        $responseData['checkout_url'] = $gcashPayment['attributes']['checkout_url'];
                        $responseData['message'] = 'GCash checkout created successfully';
                    }
                } catch (\Exception $e) {
                    Log::error('❌ GCash payment creation failed: ' . $e->getMessage());
                    // For GCash, we can still proceed with manual process if API fails
                    $responseData['message'] = 'GCash payment created. Please check your email for payment instructions.';
                }
            }
            elseif ($validated['payment_method'] === 'bank_transfer') {
                Log::info('💰 Bank transfer payment created');
                $responseData['message'] = 'Bank transfer payment created. Please check your email for bank account details.';
                // Send bank transfer instructions email
                $this->sendBankTransferInstructions($payment);
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
                        'amount' => (int)($payment->amount * 100), // Convert to cents
                        'description' => 'Payment for Booking #' . $payment->booking->booking_number,
                        'remarks' => 'Shipping booking payment',
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
                return $data['data'];
            } else {
                Log::error('❌ PayMongo Payment Link Error: ' . $response->body());
                throw new \Exception('Failed to create payment link: ' . $response->body());
            }

        } catch (\Exception $e) {
            Log::error('💥 Payment Link Creation Failed: ' . $e->getMessage());
            throw $e;
        }
    }

    private function createGCashPayment(Payment $payment)
    {
        try {
            Log::info('🔍 Creating GCash Payment via PayMongo');
            
            // Use PayMongo to create a GCash payment
            $payload = [
                'data' => [
                    'attributes' => [
                        'amount' => (int)($payment->amount * 100),
                        'description' => 'GCash Payment for Booking #' . $payment->booking->booking_number,
                        'remarks' => 'GCash payment for shipping booking',
                        'type' => 'gcash' // Specify GCash payment type
                    ]
                ]
            ];

            Log::info('🔍 GCash Payment Payload:', $payload);

            $response = Http::withHeaders([
                'Authorization' => 'Basic ' . base64_encode($this->paymongoSecret . ':'),
                'Content-Type' => 'application/json',
            ])
            ->timeout(30)
            ->post($this->paymongoUrl . '/links', $payload);

            Log::info('🔍 GCash Payment Response Status: ' . $response->status());

            if ($response->successful()) {
                $data = $response->json();
                return $data['data'];
            } else {
                Log::error('❌ GCash Payment Error: ' . $response->body());
                throw new \Exception('Failed to create GCash payment: ' . $response->body());
            }

        } catch (\Exception $e) {
            Log::error('💥 GCash Payment Creation Failed: ' . $e->getMessage());
            throw $e;
        }
    }

    private function sendBankTransferInstructions(Payment $payment)
    {
        try {
            // Implement bank transfer email sending logic here
            Log::info('📧 Bank transfer instructions would be sent for payment: ' . $payment->id);
            
            // Example email content
            $bankDetails = [
                'bank_name' => 'Your Bank Name',
                'account_name' => 'Your Company Name',
                'account_number' => '1234567890',
                'reference' => $payment->reference_number
            ];
            
            // Mail::to($payment->user->email)->send(new BankTransferInstructions($payment, $bankDetails));
            
            return true;
        } catch (\Exception $e) {
            Log::error('Failed to send bank transfer instructions: ' . $e->getMessage());
            return false;
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
        if (in_array($payment->payment_method, ['paymongo', 'gcash']) && $payment->provider_payment_id) {
            $this->checkProviderPaymentStatus($payment);
        }

        return response()->json($payment->fresh());
    }

    private function checkProviderPaymentStatus(Payment $payment)
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Basic ' . base64_encode($this->paymongoSecret . ':'),
            ])->get($this->paymongoUrl . '/links/' . $payment->provider_payment_id);

            if ($response->successful()) {
                $data = $response->json();
                $paymentData = $data['data'];
                $status = $paymentData['attributes']['status'];

                // Update payment status based on provider status
                $paymentStatus = $this->mapProviderStatus($status);
                
                $payment->update([
                    'status' => $paymentStatus,
                    'provider_response' => $paymentData
                ]);

                // If payment is completed, update AR
                if ($paymentStatus === 'completed') {
                    $this->updateAccountsReceivable($payment);
                    $payment->update(['paid_at' => now()]);
                }
            }
        } catch (\Exception $e) {
            Log::error('Provider status check failed: ' . $e->getMessage());
        }
    }

    private function mapProviderStatus($providerStatus)
    {
        $statusMap = [
            'pending' => 'pending',
            'processing' => 'processing',
            'paid' => 'completed',
            'unpaid' => 'failed',
            'expired' => 'failed'
        ];

        return $statusMap[$providerStatus] ?? 'pending';
    }

    private function updateAccountsReceivable(Payment $payment)
    {
        $ar = AccountsReceivable::where('booking_id', $payment->booking_id)->first();
        if ($ar) {
            // Since we enforce full payments, mark as fully paid
            $ar->update([
                'collectible_amount' => 0,
                'is_paid' => true
            ]);

            // Recalculate financials
            $ar->calculateFinancials()->save();

            Log::info('✅ Accounts Receivable updated for payment: ' . $payment->id);
        }
    }
}