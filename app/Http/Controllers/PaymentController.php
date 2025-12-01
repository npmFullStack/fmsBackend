<?php

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
    private $paymongoScret;
    private $paymongoUrl;
    private $webhookSecret;

    public function __construct()
    {
        $this->paymongoSecret = config('services.paymongo.secret_key');
        $this->paymongoUrl = 'https://api.paymongo.com/v1';
        $this->webhookSecret = config('services.paymongo.webhook_secret');
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
        $paymentMethod = $request->payment_method ?? 'gcash';

        // For Cash on Delivery - create pending payment

if ($paymentMethod === 'cod') {
    $payment = Payment::create([
        'booking_id' => $booking->id,
        'user_id' => $user->id,
        'payment_method' => 'cod',
        'amount' => $paymentAmount,
        'status' => 'pending', // COD payments are pending until collected
        'payment_date' => now(),
        'reference_number' => Payment::generateReferenceNumber(),
    ]);

    Log::info('💰 COD Payment record created', [
        'payment_id' => $payment->id,
        'booking_id' => $booking->id
    ]);

    // Update Accounts Receivable to mark as COD
    $ar = AccountsReceivable::where('booking_id', $booking->id)->first();
    if ($ar) {
        $ar->update([
            'payment_method' => 'cod',
            'cod_pending' => true,
            'collectible_amount' => $paymentAmount, // Still collectible but via COD
        ]);
        
        Log::info('✅ Accounts Receivable updated for COD', [
            'ar_id' => $ar->id,
            'cod_pending' => true
        ]);
    }

    DB::commit();

    return response()->json([
        'message' => 'Cash on Delivery payment recorded successfully. Payment will be collected upon delivery.',
        'payment_id' => $payment->id,
        'status' => 'pending',
        'cod_pending' => true // Add this flag
    ], 201);
}

        // For GCash - create Paymongo payment link
        if ($paymentMethod === 'gcash') {
            // Create payment record
            $payment = Payment::create([
                'booking_id' => $booking->id,
                'user_id' => $user->id,
                'payment_method' => 'gcash',
                'amount' => $paymentAmount,
                'status' => 'processing',
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
        }

        // If payment method is not supported
        return response()->json(['message' => 'Unsupported payment method'], 400);

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
     * Handle Paymongo webhook with signature verification
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
            // Verify webhook signature
            if (!$this->verifyWebhookSignature($payload, $signature)) {
                Log::error('❌ Webhook signature verification failed');
                return response()->json(['error' => 'Invalid signature'], 401);
            }

            $data = json_decode($payload, true);
            $eventType = $data['data']['attributes']['type'] ?? null;
            
            Log::info('🔍 Webhook Event', ['event_type' => $eventType]);

            // Handle different event types
            if ($eventType === 'payment.paid') {
                $this->handlePaymentPaid($data);
            } elseif ($eventType === 'payment.failed') {
                $this->handlePaymentFailed($data);
            } elseif ($eventType === 'source.chargeable') {
                $this->handleSourceChargeable($data);
            } elseif ($eventType === 'payment.pending') {
                Log::info('⏳ Payment pending', ['payment_id' => $data['data']['id'] ?? null]);
            }

            return response()->json(['status' => 'success']);

        } catch (\Exception $e) {
            Log::error('💥 Webhook processing error: ' . $e->getMessage());
            return response()->json(['error' => 'Webhook processing failed'], 500);
        }
    }

    /**
     * Verify Paymongo webhook signature
     */
    private function verifyWebhookSignature($payload, $signature)
    {
        try {
            $webhookSecret = $this->webhookSecret;
            
            if (!$webhookSecret) {
                Log::error('❌ Webhook secret not configured');
                return false;
            }

            // Extract timestamp and signatures from header
            $parts = explode(',', $signature);
            $timestamp = null;
            $signatures = [];
            
            foreach ($parts as $part) {
                if (strpos($part, 't=') === 0) {
                    $timestamp = substr($part, 2);
                } elseif (strpos($part, 'v1=') === 0) {
                    $signatures[] = substr($part, 3);
                }
            }

            if (!$timestamp || empty($signatures)) {
                Log::error('❌ Invalid signature format');
                return false;
            }

            // Verify timestamp (within 5 minutes)
            if (abs(time() - $timestamp) > 300) {
                Log::error('❌ Webhook timestamp expired');
                return false;
            }

            // Compute expected signature
            $signedPayload = $timestamp . '.' . $payload;
            $expectedSignature = hash_hmac('sha256', $signedPayload, $webhookSecret);

            // Check if any of the signatures match
            foreach ($signatures as $signature) {
                if (hash_equals($expectedSignature, $signature)) {
                    Log::info('✅ Webhook signature verified');
                    return true;
                }
            }

            Log::error('❌ No matching signature found');
            return false;

        } catch (\Exception $e) {
            Log::error('💥 Webhook verification error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Create Paymongo payment link
     */
    private function createPaymongoPaymentLink(Payment $payment, Booking $booking)
{
    try {
        Log::info('🔍 Creating Paymongo payment link for booking: ' . $booking->id);
        
        $payload = [
            'data' => [
                'attributes' => [
                    'amount' => (int)($payment->amount * 100), // Convert to cents
                    'description' => 'Payment for Booking #' . $booking->booking_number,
                    'remarks' => 'Shipping booking payment via GCash',
                    'metadata' => [
                        'booking_id' => $booking->id,
                        'payment_id' => $payment->id,
                        'booking_number' => $booking->booking_number,
                        'user_id' => $payment->user_id,
                        'user_email' => $payment->user->email ?? 'customer@example.com'
                    ],
                    'checkout' => [
                        'success' => env('APP_URL') . '/payment/success',
                        'cancel' => env('APP_URL') . '/payment/cancel',
                    ]
                ]
            ]
        ];

        Log::info('🔍 Paymongo request payload:', $payload);

        $response = Http::withHeaders([
            'Authorization' => 'Basic ' . base64_encode($this->paymongoSecret . ':'),
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ])->timeout(30)
          ->post($this->paymongoUrl . '/links', $payload);

        Log::info('🔍 Paymongo response status: ' . $response->status());
        Log::info('🔍 Paymongo response body: ' . $response->body());

        if ($response->successful()) {
            $data = $response->json();
            Log::info('✅ Paymongo payment link created', [
                'payment_id' => $data['data']['id'],
                'checkout_url' => $data['data']['attributes']['checkout_url'] ?? null
            ]);
            
            if (isset($data['data']['attributes']['checkout_url'])) {
                return [
                    'id' => $data['data']['id'],
                    'checkout_url' => $data['data']['attributes']['checkout_url']
                ];
            } else {
                Log::error('❌ No checkout_url in Paymongo response');
                throw new \Exception('No checkout URL returned from Paymongo');
            }
        } else {
            $errorBody = $response->body();
            Log::error('❌ Paymongo API error: ' . $errorBody);
            
            // Try to parse error
            $errorData = json_decode($errorBody, true);
            $errorMessage = $errorData['errors'][0]['detail'] ?? 'Unknown Paymongo error';
            
            throw new \Exception('Paymongo API error: ' . $errorMessage);
        }

    } catch (\Exception $e) {
        Log::error('💥 Paymongo payment link creation failed: ' . $e->getMessage());
        throw new \Exception('Failed to create Paymongo payment link: ' . $e->getMessage());
    }
}

    /**
     * Handle successful payment
     */
    private function handlePaymentPaid($data)
    {
        try {
            $paymentIntentId = $data['data']['attributes']['data']['id'] ?? null;
            
            if (!$paymentIntentId) {
                Log::error('❌ Payment intent ID not found in webhook');
                return;
            }

            Log::info('💰 Processing paid payment for intent: ' . $paymentIntentId);

            // Find payment by paymongo_payment_id
            $payment = Payment::where('paymongo_payment_id', $paymentIntentId)->first();
            
            if ($payment) {
                DB::transaction(function () use ($payment, $data) {
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
                });
            } else {
                Log::error('❌ Payment not found for intent: ' . $paymentIntentId);
                // Try to find by metadata
                $this->findPaymentByMetadata($data);
            }

        } catch (\Exception $e) {
            Log::error('💥 Error handling payment paid: ' . $e->getMessage());
        }
    }

    /**
     * Find payment by metadata if direct ID lookup fails
     */
    private function findPaymentByMetadata($data)
    {
        try {
            $metadata = $data['data']['attributes']['data']['attributes']['metadata'] ?? [];
            $paymentId = $metadata['payment_id'] ?? null;
            $bookingId = $metadata['booking_id'] ?? null;

            if ($paymentId) {
                $payment = Payment::find($paymentId);
                if ($payment) {
                    DB::transaction(function () use ($payment, $data) {
                        $payment->update([
                            'status' => 'paid',
                            'paid_at' => now(),
                            'payment_date' => now(),
                            'paymongo_response' => $data,
                            'paymongo_payment_id' => $data['data']['attributes']['data']['id'] ?? null,
                        ]);

                        // Update Accounts Receivable
                        $ar = AccountsReceivable::where('booking_id', $payment->booking_id)->first();
                        if ($ar) {
                            $ar->update([
                                'collectible_amount' => 0,
                                'is_paid' => true
                            ]);
                        }

                        Log::info('✅ Payment found via metadata and marked as PAID', [
                            'payment_id' => $payment->id,
                            'booking_id' => $payment->booking_id
                        ]);
                    });
                    return;
                }
            }

            // If still not found, log all metadata for debugging
            Log::error('❌ Payment not found via metadata', ['metadata' => $metadata]);

        } catch (\Exception $e) {
            Log::error('💥 Error finding payment by metadata: ' . $e->getMessage());
        }
    }

    /**
     * Handle failed payment
     */
    private function handlePaymentFailed($data)
    {
        try {
            $paymentIntentId = $data['data']['attributes']['data']['id'] ?? null;
            
            if ($paymentIntentId) {
                $payment = Payment::where('paymongo_payment_id', $paymentIntentId)->first();
                
                if ($payment) {
                    $payment->update([
                        'status' => 'failed',
                        'paymongo_response' => $data,
                    ]);
                    
                    Log::info('❌ Payment marked as failed', ['payment_id' => $payment->id]);
                } else {
                    // Try to find by metadata
                    $this->findAndFailPaymentByMetadata($data);
                }
            }
        } catch (\Exception $e) {
            Log::error('💥 Error handling payment failed: ' . $e->getMessage());
        }
    }

    /**
     * Find and mark payment as failed by metadata
     */
    private function findAndFailPaymentByMetadata($data)
    {
        try {
            $metadata = $data['data']['attributes']['data']['attributes']['metadata'] ?? [];
            $paymentId = $metadata['payment_id'] ?? null;

            if ($paymentId) {
                $payment = Payment::find($paymentId);
                if ($payment) {
                    $payment->update([
                        'status' => 'failed',
                        'paymongo_response' => $data,
                    ]);
                    
                    Log::info('❌ Payment found via metadata and marked as failed', ['payment_id' => $payment->id]);
                }
            }
        } catch (\Exception $e) {
            Log::error('💥 Error finding and failing payment by metadata: ' . $e->getMessage());
        }
    }

    /**
     * Handle source chargeable (for GCash, GrabPay, etc.)
     */
    private function handleSourceChargeable($data)
    {
        try {
            $sourceId = $data['data']['id'] ?? null;
            $paymentIntentId = $data['data']['attributes']['data']['attributes']['payment_intent_id'] ?? null;
            
            Log::info('🔌 Source chargeable', [
                'source_id' => $sourceId,
                'payment_intent_id' => $paymentIntentId
            ]);

            // For GCash and other payment methods that require source charging
            if ($sourceId && $paymentIntentId) {
                Log::info('✅ Source is chargeable, waiting for payment confirmation', [
                    'source_id' => $sourceId,
                    'payment_intent_id' => $paymentIntentId
                ]);
            }

        } catch (\Exception $e) {
            Log::error('💥 Error handling source chargeable: ' . $e->getMessage());
        }
    }
}