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
        $this->paymongoUrl = 'https://api.paymongo.com/v1';
    }

    /**
     * Display a listing of payments (admin)
     */
    public function index(Request $request)
    {
        $perPage = $request->get('per_page', 10);
        $payments = Payment::with(['booking', 'user'])
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);

        return response()->json($payments);
    }

    /**
     * Create payment for booking (customer)
     */
    public function createPayment(Request $request, $bookingId)
    {
        DB::beginTransaction();

        try {
            Log::info('💰 PAYMENT CREATION STARTED', $request->all());

            $user = auth()->user();
            $booking = Booking::find($bookingId);

            if (!$booking) {
                return response()->json(['message' => 'Booking not found'], 404);
            }

            // Check if user owns the booking
            if ($booking->user_id !== $user->id) {
                return response()->json(['message' => 'Unauthorized access to this booking'], 403);
            }

            // Check AR record
            $ar = AccountsReceivable::where('booking_id', $booking->id)->first();
            if (!$ar) {
                return response()->json(['message' => 'No accounts receivable record found'], 404);
            }

            $paymentAmount = $ar->collectible_amount;

            // Create payment record
            $payment = Payment::create([
                'booking_id' => $booking->id,
                'user_id' => $user->id,
                'payment_method' => 'gcash',
                'amount' => $paymentAmount,
                'status' => 'pending',
                'payment_date' => now(),
            ]);

            Log::info('💰 Payment record created', ['payment_id' => $payment->id]);

            // Create Paymongo payment link
            $paymongoResponse = $this->createPaymongoPaymentLink($payment, $booking);
            
            if ($paymongoResponse && isset($paymongoResponse['checkout_url'])) {
                $payment->update([
                    'paymongo_payment_id' => $paymongoResponse['id'],
                    'paymongo_checkout_url' => $paymongoResponse['checkout_url'],
                    'paymongo_response' => $paymongoResponse,
                    'status' => 'processing'
                ]);

                DB::commit();

                return response()->json([
                    'message' => 'Payment checkout created successfully',
                    'checkout_url' => $paymongoResponse['checkout_url'],
                    'payment_id' => $payment->id
                ], 201);
            } else {
                throw new \Exception('Failed to create Paymongo payment link');
            }

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('💥 PAYMENT CREATION FAILED: ' . $e->getMessage());
            return response()->json([
                'message' => 'Failed to create payment',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store a newly created payment (legacy method)
     */
    public function store(Request $request)
    {
        // You can keep this for admin-created payments or redirect to createPayment
        return $this->createPayment($request, $request->booking_id);
    }

    /**
     * Display the specified payment
     */
    public function show($id)
    {
        $payment = Payment::with(['booking', 'user'])->find($id);

        if (!$payment) {
            return response()->json(['message' => 'Payment not found'], 404);
        }

        return response()->json($payment);
    }

    /**
     * Update the specified payment
     */
    public function update(Request $request, $id)
    {
        $payment = Payment::find($id);

        if (!$payment) {
            return response()->json(['message' => 'Payment not found'], 404);
        }

        $validated = $request->validate([
            'status' => 'sometimes|in:pending,processing,paid,failed,cancelled',
        ]);

        $payment->update($validated);

        return response()->json($payment);
    }

    /**
     * Remove the specified payment
     */
    public function destroy($id)
    {
        $payment = Payment::find($id);

        if (!$payment) {
            return response()->json(['message' => 'Payment not found'], 404);
        }

        $payment->delete();

        return response()->json(['message' => 'Payment deleted successfully']);
    }

    /**
     * Get payments by booking
     */
    public function getByBooking($bookingId)
    {
        $payments = Payment::with(['user'])
            ->where('booking_id', $bookingId)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($payments);
    }

    /**
     * Process GCash payment (legacy method)
     */
    public function processGCashPayment(Request $request, $id)
    {
        // You can keep this for alternative GCash processing
        return response()->json(['message' => 'Use createPayment instead']);
    }

    /**
     * Check payment status
     */
    public function checkPaymentStatus($id)
    {
        try {
            $payment = Payment::find($id);

            if (!$payment) {
                return response()->json(['message' => 'Payment not found'], 404);
            }

            return response()->json([
                'payment' => $payment,
                'status' => $payment->status
            ]);

        } catch (\Exception $e) {
            Log::error('💥 Payment status check error: ' . $e->getMessage());
            return response()->json(['message' => 'Status check failed'], 500);
        }
    }

    /**
     * Handle Paymongo webhook
     */
    public function handleWebhook(Request $request)
    {
        $payload = $request->getContent();
        $signature = $request->header('Paymongo-Signature');
        
        Log::info('💰 PAYMONGO WEBHOOK RECEIVED', [
            'payload' => $payload,
            'signature' => $signature
        ]);

        try {
            $data = json_decode($payload, true);
            $eventType = $data['data']['attributes']['type'] ?? null;
            
            Log::info('🔍 Webhook Event', ['event_type' => $eventType]);

            if ($eventType === 'link.payment.paid') {
                $this->handlePaymentPaid($data);
            } elseif ($eventType === 'link.payment.failed') {
                $this->handlePaymentFailed($data);
            }

            return response()->json(['status' => 'success']);

        } catch (\Exception $e) {
            Log::error('💥 Webhook processing error: ' . $e->getMessage());
            return response()->json(['error' => 'Webhook processing failed'], 500);
        }
    }

    /**
     * Create Paymongo payment link
     */
    private function createPaymongoPaymentLink(Payment $payment, Booking $booking)
    {
        try {
            $payload = [
                'data' => [
                    'attributes' => [
                        'amount' => (int)($payment->amount * 100),
                        'description' => 'Payment for Booking #' . $booking->booking_number,
                        'remarks' => 'Shipping booking payment',
                    ]
                ]
            ];

            Log::info('🔍 Creating Paymongo payment link', $payload);

            $response = Http::withHeaders([
                'Authorization' => 'Basic ' . base64_encode($this->paymongoSecret . ':'),
                'Content-Type' => 'application/json',
            ])->timeout(30)
              ->post($this->paymongoUrl . '/links', $payload);

            if ($response->successful()) {
                $data = $response->json();
                Log::info('✅ Paymongo payment link created', ['payment_id' => $data['data']['id']]);
                return $data['data'];
            } else {
                Log::error('❌ Paymongo API error: ' . $response->body());
                throw new \Exception('Paymongo API error: ' . $response->body());
            }

        } catch (\Exception $e) {
            Log::error('💥 Paymongo payment link creation failed: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Handle successful payment
     */
    private function handlePaymentPaid($data)
    {
        try {
            $paymentLinkId = $data['data']['attributes']['data']['id'] ?? null;
            
            if (!$paymentLinkId) {
                Log::error('❌ Payment link ID not found in webhook');
                return;
            }

            Log::info('💰 Processing paid payment for link: ' . $paymentLinkId);

            // Find payment by paymongo_payment_id
            $payment = Payment::where('paymongo_payment_id', $paymentLinkId)->first();
            
            if ($payment) {
                // Update payment status to PAID
                $payment->update([
                    'status' => 'paid',
                    'paid_at' => now(),
                    'payment_date' => now(),
                    'paymongo_response' => $data,
                ]);

                // Update Accounts Receivable
                $ar = AccountsReceivable::where('booking_id', $payment->booking_id)->first();
                if ($ar) {
                    $ar->update([
                        'collectible_amount' => 0,
                        'is_paid' => true
                    ]);

                    Log::info('✅ Accounts Receivable updated for booking: ' . $payment->booking_id);
                }

                Log::info('✅ Payment marked as PAID', [
                    'payment_id' => $payment->id,
                    'amount' => $payment->amount,
                    'booking_id' => $payment->booking_id
                ]);
            } else {
                Log::error('❌ Payment not found for link: ' . $paymentLinkId);
            }

        } catch (\Exception $e) {
            Log::error('💥 Error handling payment paid: ' . $e->getMessage());
        }
    }

    /**
     * Handle failed payment
     */
    private function handlePaymentFailed($data)
    {
        try {
            $paymentLinkId = $data['data']['attributes']['data']['id'] ?? null;
            
            if ($paymentLinkId) {
                $payment = Payment::where('paymongo_payment_id', $paymentLinkId)->first();
                
                if ($payment) {
                    $payment->update([
                        'status' => 'failed',
                        'paymongo_response' => $data,
                    ]);
                    
                    Log::info('❌ Payment marked as failed', ['payment_id' => $payment->id]);
                }
            }
        } catch (\Exception $e) {
            Log::error('💥 Error handling payment failed: ' . $e->getMessage());
        }
    }
}