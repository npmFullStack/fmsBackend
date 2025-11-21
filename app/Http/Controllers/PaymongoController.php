<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\Payment;
use App\Models\AccountsReceivable;
use App\Models\Booking;

class PaymongoController extends Controller
{
    private $secretKey;
    private $publicKey;
    private $baseUrl;

    public function __construct()
    {
        $this->secretKey = config('services.paymongo.secret_key');
        $this->publicKey = config('services.paymongo.public_key');
        $this->baseUrl = config('services.paymongo.url', 'https://api.paymongo.com/v1');
    }

    /**
     * Create a payment intent for Paymongo
     */
    public function createPaymentIntent(Request $request)
    {
        $validated = $request->validate([
            'booking_id' => 'required|exists:bookings,id',
            'amount' => 'required|numeric|min:1',
            'description' => 'nullable|string',
        ]);

        try {
            $booking = Booking::find($validated['booking_id']);
            $ar = AccountsReceivable::where('booking_id', $validated['booking_id'])->first();

            if (!$ar) {
                return response()->json(['message' => 'Accounts receivable record not found'], 404);
            }

            // Convert amount to cents (Paymongo uses smallest currency unit)
            $amount = $validated['amount'] * 100;

            $response = Http::withHeaders([
                'Authorization' => 'Basic ' . base64_encode($this->secretKey . ':'),
                'Content-Type' => 'application/json',
            ])->post($this->baseUrl . '/payment_intents', [
                'data' => [
                    'attributes' => [
                        'amount' => $amount,
                        'payment_method_allowed' => ['card', 'gcash', 'grab_pay'],
                        'payment_method_options' => [
                            'card' => [
                                'request_three_d_secure' => 'automatic'
                            ]
                        ],
                        'currency' => 'PHP',
                        'description' => $validated['description'] ?? 'Payment for Booking #' . $booking->booking_number,
                        'metadata' => [
                            'booking_id' => $booking->id,
                            'booking_number' => $booking->booking_number,
                            'user_id' => $booking->user_id,
                        ]
                    ]
                ]
            ]);

            if ($response->successful()) {
                $data = $response->json();
                $paymentIntent = $data['data'];

                // Create pending payment record
                $payment = Payment::create([
                    'booking_id' => $booking->id,
                    'user_id' => $booking->user_id,
                    'amount' => $validated['amount'],
                    'payment_method' => 'paymongo',
                    'reference_number' => $paymentIntent['id'],
                    'status' => 'pending',
                    'paymongo_payment_intent_id' => $paymentIntent['id'],
                    'payment_date' => now(),
                ]);

                return response()->json([
                    'client_key' => $paymentIntent['attributes']['client_key'],
                    'payment_intent_id' => $paymentIntent['id'],
                    'amount' => $validated['amount'],
                    'payment' => $payment
                ]);
            } else {
                Log::error('Paymongo API Error: ' . $response->body());
                return response()->json([
                    'message' => 'Failed to create payment intent',
                    'error' => $response->json()
                ], 400);
            }

        } catch (\Exception $e) {
            Log::error('Paymongo Exception: ' . $e->getMessage());
            return response()->json([
                'message' => 'Payment processing failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Handle Paymongo webhook
     */
    public function handleWebhook(Request $request)
    {
        $payload = $request->getContent();
        $signature = $request->header('Paymongo-Signature');
        
        Log::info('Paymongo Webhook Received', [
            'payload' => $payload,
            'signature' => $signature
        ]);

        try {
            // Verify webhook signature
            if (!$this->verifyWebhookSignature($payload, $signature)) {
                Log::error('Invalid webhook signature');
                return response()->json(['error' => 'Invalid signature'], 400);
            }

            $data = json_decode($payload, true);
            $eventType = $data['data']['attributes']['type'] ?? null;
            
            if ($eventType === 'payment.paid') {
                $this->handlePaymentPaid($data);
            } elseif ($eventType === 'payment.failed') {
                $this->handlePaymentFailed($data);
            }

            return response()->json(['status' => 'success']);

        } catch (\Exception $e) {
            Log::error('Webhook processing error: ' . $e->getMessage());
            return response()->json(['error' => 'Webhook processing failed'], 500);
        }
    }

    /**
     * Verify webhook signature
     */
    private function verifyWebhookSignature($payload, $signature)
    {
        // You need to set this in your .env
        $webhookSecret = config('services.paymongo.webhook_secret');
        
        if (!$webhookSecret) {
            Log::warning('Paymongo webhook secret not set');
            return true; // For development, you might want to disable verification
        }

        $computedSignature = hash_hmac('sha256', $payload, $webhookSecret);
        
        return hash_equals($signature, $computedSignature);
    }

    /**
     * Handle successful payment
     */
    private function handlePaymentPaid($data)
    {
        $paymentIntentId = $data['data']['attributes']['data']['id'] ?? null;
        
        if (!$paymentIntentId) {
            Log::error('Payment intent ID not found in webhook');
            return;
        }

        $payment = Payment::where('paymongo_payment_intent_id', $paymentIntentId)->first();
        
        if ($payment) {
            $payment->update([
                'status' => 'completed',
                'paymongo_response' => $data,
            ]);

            // Update Accounts Receivable
            $ar = AccountsReceivable::where('booking_id', $payment->booking_id)->first();
            if ($ar) {
                $remainingAmount = $ar->collectible_amount - $payment->amount;
                
                if ($remainingAmount <= 0) {
                    $ar->markAsPaid();
                } else {
                    $ar->update([
                        'collectible_amount' => $remainingAmount,
                        'is_paid' => false,
                    ]);
                }

                // Send payment confirmation email
                $this->sendPaymentConfirmation($ar, $payment);
            }

            Log::info('Payment marked as completed', ['payment_id' => $payment->id]);
        }
    }

    /**
     * Handle failed payment
     */
    private function handlePaymentFailed($data)
    {
        $paymentIntentId = $data['data']['attributes']['data']['id'] ?? null;
        
        if ($paymentIntentId) {
            $payment = Payment::where('paymongo_payment_intent_id', $paymentIntentId)->first();
            
            if ($payment) {
                $payment->update([
                    'status' => 'failed',
                    'paymongo_response' => $data,
                ]);
                
                Log::info('Payment marked as failed', ['payment_id' => $payment->id]);
            }
        }
    }

    private function sendPaymentConfirmation($ar, $payment)
    {
        // Implement email sending logic here
        // You can use similar logic as in AccountsReceivableController
    }
}