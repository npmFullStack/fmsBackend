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
        $booking = Booking::with('user')->find($bookingId);

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

        // Validate payment amount
        if ($paymentAmount <= 0) {
            return response()->json(['message' => 'No payment amount due'], 400);
        }

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
                'booking_id' => $booking->id,
                'amount' => $paymentAmount
            ]);

            // Update Accounts Receivable to mark as COD
            $ar->update([
                'payment_method' => 'cod',
                'cod_pending' => true,
                'collectible_amount' => $paymentAmount, // Still collectible but via COD
            ]);
            
            Log::info('✅ Accounts Receivable updated for COD', [
                'ar_id' => $ar->id,
                'cod_pending' => true
            ]);

            DB::commit();

            return response()->json([
                'message' => 'Cash on Delivery payment recorded successfully. Payment will be collected upon delivery.',
                'payment_id' => $payment->id,
                'status' => 'pending',
                'cod_pending' => true
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
                'reference_number' => Payment::generateReferenceNumber(),
            ]);

            Log::info('💰 Payment record created for GCash', [
                'payment_id' => $payment->id,
                'amount' => $paymentAmount
            ]);

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
                    'payment_id' => $payment->id,
                    'booking_number' => $booking->booking_number,
                    'amount' => $paymentAmount
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
        Log::error('💥 Stack trace: ' . $e->getTraceAsString());
        
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

        if (!$signature) {
            Log::error('❌ No signature provided in webhook');
            return false;
        }

        // Extract timestamp and signatures from header
        $parts = explode(',', $signature);
        $timestamp = null;
        $signatures = [];
        
        foreach ($parts as $part) {
            $part = trim($part);
            if (strpos($part, 't=') === 0) {
                $timestamp = substr($part, 2);
            } elseif (strpos($part, 'v1=') === 0) {
                $signatures[] = substr($part, 3);
            }
        }

        if (!$timestamp || empty($signatures)) {
            Log::error('❌ Invalid signature format');
            Log::error('❌ Signature header: ' . $signature);
            return false;
        }

        // Verify timestamp (within 5 minutes)
        if (abs(time() - $timestamp) > 300) {
            Log::error('❌ Webhook timestamp expired. Server time: ' . time() . ', Timestamp: ' . $timestamp);
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
        Log::error('❌ Expected: ' . $expectedSignature);
        Log::error('❌ Received: ' . implode(', ', $signatures));
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
        
        // Get user email from booking relationship
        $userEmail = $booking->user->email ?? 'customer@example.com';
        
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
                        'user_email' => $userEmail
                    ],
                    'checkout' => [
                        'success' => env('APP_URL') . '/payment/success?payment_id=' . $payment->id,
                        'cancel' => env('APP_URL') . '/payment/cancel?payment_id=' . $payment->id,
                    ],
                    'payment_method_types' => ['gcash'],
                    'send_email_receipt' => true,
                    'description' => 'Payment for Booking #' . $booking->booking_number,
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
        
        if ($response->successful()) {
            $data = $response->json();
            
            if (isset($data['data']['attributes']['checkout_url'])) {
                Log::info('✅ Paymongo payment link created', [
                    'payment_id' => $data['data']['id'],
                    'checkout_url' => $data['data']['attributes']['checkout_url']
                ]);
                
                return [
                    'id' => $data['data']['id'],
                    'checkout_url' => $data['data']['attributes']['checkout_url'],
                    'reference_number' => $data['data']['attributes']['reference_number'] ?? null
                ];
            } else {
                Log::error('❌ No checkout_url in Paymongo response');
                Log::error('❌ Full response:', $data);
                throw new \Exception('No checkout URL returned from Paymongo');
            }
        } else {
            $errorBody = $response->body();
            Log::error('❌ Paymongo API error: ' . $errorBody);
            
            // Try to parse error
            $errorData = json_decode($errorBody, true);
            $errorMessage = 'Unknown Paymongo error';
            
            if (isset($errorData['errors'][0]['detail'])) {
                $errorMessage = $errorData['errors'][0]['detail'];
            } elseif (isset($errorData['errors'][0]['code'])) {
                $errorMessage = $errorData['errors'][0]['code'];
            }
            
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
        $paymentData = $data['data']['attributes']['data'] ?? $data['data'];
        $paymentIntentId = $paymentData['id'] ?? null;
        
        if (!$paymentIntentId) {
            Log::error('❌ Payment intent ID not found in webhook');
            Log::error('❌ Webhook data:', $data);
            return;
        }

        Log::info('💰 Processing paid payment for intent: ' . $paymentIntentId);

        // First try to find payment by paymongo_payment_id
        $payment = Payment::where('paymongo_payment_id', $paymentIntentId)->first();
        
        if (!$payment) {
            // Try to find by metadata
            $metadata = $paymentData['attributes']['metadata'] ?? [];
            $paymentId = $metadata['payment_id'] ?? null;
            
            if ($paymentId) {
                $payment = Payment::find($paymentId);
            }
        }

        if ($payment) {
            DB::transaction(function () use ($payment, $data, $paymentIntentId) {
                // Update payment status to PAID
                $payment->update([
                    'status' => 'paid',
                    'paid_at' => now(),
                    'payment_date' => now(),
                    'paymongo_response' => $data,
                    'paymongo_payment_id' => $paymentIntentId,
                ]);

                // Update Accounts Receivable
                $ar = AccountsReceivable::where('booking_id', $payment->booking_id)->first();
                if ($ar) {
                    $ar->update([
                        'collectible_amount' => 0,
                        'is_paid' => true,
                        'cod_pending' => false, // Reset COD flag if it was set
                        'payment_method' => 'gcash' // Update payment method to GCash
                    ]);

                    Log::info('✅ Accounts Receivable updated for booking: ' . $payment->booking_id, [
                        'ar_id' => $ar->id,
                        'collectible_amount' => 0,
                        'is_paid' => true
                    ]);
                }

                // Update booking status if needed
                $booking = Booking::find($payment->booking_id);
                if ($booking) {
                    // You can update booking status here if needed
                    Log::info('✅ Booking payment completed', [
                        'booking_id' => $booking->id,
                        'booking_number' => $booking->booking_number
                    ]);
                }

                Log::info('✅ Payment marked as PAID', [
                    'payment_id' => $payment->id,
                    'amount' => $payment->amount,
                    'booking_id' => $payment->booking_id
                ]);
            });
        } else {
            Log::error('❌ Payment not found for intent: ' . $paymentIntentId);
            Log::error('❌ Metadata:', $paymentData['attributes']['metadata'] ?? []);
            
            // Create a placeholder payment record for tracking
            $metadata = $paymentData['attributes']['metadata'] ?? [];
            $bookingId = $metadata['booking_id'] ?? null;
            
            if ($bookingId) {
                $payment = Payment::create([
                    'paymongo_payment_id' => $paymentIntentId,
                    'booking_id' => $bookingId,
                    'user_id' => $metadata['user_id'] ?? null,
                    'payment_method' => 'gcash',
                    'amount' => $paymentData['attributes']['amount'] / 100, // Convert from cents
                    'status' => 'paid',
                    'paid_at' => now(),
                    'payment_date' => now(),
                    'paymongo_response' => $data,
                    'reference_number' => Payment::generateReferenceNumber(),
                ]);
                
                Log::info('⚠️ Created missing payment record from webhook', [
                    'payment_id' => $payment->id,
                    'booking_id' => $bookingId
                ]);
            }
        }

    } catch (\Exception $e) {
        Log::error('💥 Error handling payment paid: ' . $e->getMessage());
        Log::error('💥 Stack trace: ' . $e->getTraceAsString());
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