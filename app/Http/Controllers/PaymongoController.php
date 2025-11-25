<?php
// [file name]: PaymongoController.php

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
            // Verify webhook signature
            if (!$this->verifyWebhookSignature($payload, $signature)) {
                Log::error('❌ Invalid webhook signature');
                return response()->json(['error' => 'Invalid signature'], 400);
            }

            $data = json_decode($payload, true);
            $eventType = $data['data']['attributes']['type'] ?? null;
            $resourceType = $data['data']['attributes']['data']['attributes']['type'] ?? null;
            
            Log::info('🔍 Webhook Event Details', [
                'event_type' => $eventType,
                'resource_type' => $resourceType
            ]);

            if ($eventType === 'link.payment.paid') {
                $this->handlePaymentPaid($data);
            } elseif ($eventType === 'link.payment.failed') {
                $this->handlePaymentFailed($data);
            } elseif ($eventType === 'link.payment.expired') {
                $this->handlePaymentExpired($data);
            } else {
                Log::info('🔔 Unhandled webhook event: ' . $eventType);
            }

            return response()->json(['status' => 'success']);

        } catch (\Exception $e) {
            Log::error('💥 Webhook processing error: ' . $e->getMessage());
            return response()->json(['error' => 'Webhook processing failed'], 500);
        }
    }

    /**
     * Verify webhook signature
     */
    private function verifyWebhookSignature($payload, $signature)
    {
        $webhookSecret = config('services.paymongo.webhook_secret');
        
        // For development, skip verification
        if (app()->environment('local') || app()->environment('development')) {
            Log::info('🔓 Webhook verification skipped for development');
            return true;
        }
        
        if (!$webhookSecret) {
            Log::warning('⚠️ Paymongo webhook secret not set');
            return false;
        }

        $computedSignature = hash_hmac('sha256', $payload, $webhookSecret);
        
        return hash_equals($signature, $computedSignature);
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

            // Find payment by provider_payment_id
            $payment = Payment::where('provider_payment_id', $paymentLinkId)->first();
            
            if ($payment) {
                Log::info('✅ Payment found: ' . $payment->id);

                // Update payment status
                $payment->update([
                    'status' => 'completed',
                    'paid_at' => now(),
                    'payment_date' => now(),
                    'provider_response' => $data,
                ]);

                // Update Accounts Receivable
                $ar = AccountsReceivable::where('booking_id', $payment->booking_id)->first();
                if ($ar) {
                    // Mark as fully paid since we enforce full payments
                    $ar->update([
                        'collectible_amount' => 0,
                        'is_paid' => true
                    ]);

                    // Recalculate financials
                    $ar->calculateFinancials()->save();

                    Log::info('✅ Accounts Receivable updated for booking: ' . $payment->booking_id);
                }

                // Send payment confirmation email
                $this->sendPaymentConfirmation($payment);

                Log::info('✅ Payment marked as completed', [
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
                $payment = Payment::where('provider_payment_id', $paymentLinkId)->first();
                
                if ($payment) {
                    $payment->update([
                        'status' => 'failed',
                        'failed_at' => now(),
                        'provider_response' => $data,
                    ]);
                    
                    Log::info('❌ Payment marked as failed', ['payment_id' => $payment->id]);
                }
            }
        } catch (\Exception $e) {
            Log::error('💥 Error handling payment failed: ' . $e->getMessage());
        }
    }

    /**
     * Handle expired payment
     */
    private function handlePaymentExpired($data)
    {
        try {
            $paymentLinkId = $data['data']['attributes']['data']['id'] ?? null;
            
            if ($paymentLinkId) {
                $payment = Payment::where('provider_payment_id', $paymentLinkId)->first();
                
                if ($payment) {
                    $payment->update([
                        'status' => 'failed',
                        'failed_at' => now(),
                        'provider_response' => $data,
                    ]);
                    
                    Log::info('⏰ Payment marked as expired', ['payment_id' => $payment->id]);
                }
            }
        } catch (\Exception $e) {
            Log::error('💥 Error handling payment expired: ' . $e->getMessage());
        }
    }

    /**
     * Send payment confirmation email
     */
    private function sendPaymentConfirmation(Payment $payment)
    {
        try {
            $booking = $payment->booking;
            $user = $payment->user;
            
            Log::info('📧 Payment confirmation email would be sent to: ' . $user->email, [
                'payment_id' => $payment->id,
                'booking_id' => $booking->id,
                'amount' => $payment->amount
            ]);

            // Uncomment when you have the Mailable class
            // Mail::to($user->email)->send(new \App\Mail\PaymentConfirmation($payment));
            
        } catch (\Exception $e) {
            Log::error('❌ Failed to send payment confirmation: ' . $e->getMessage());
        }
    }

    /**
     * Get payment link status
     */
    public function getPaymentStatus($paymentLinkId)
    {
        try {
            Log::info('🔍 Checking payment status for link: ' . $paymentLinkId);

            $response = Http::withHeaders([
                'Authorization' => 'Basic ' . base64_encode($this->secretKey . ':'),
            ])->get($this->baseUrl . '/links/' . $paymentLinkId);

            if ($response->successful()) {
                $data = $response->json();
                Log::info('✅ Payment status retrieved', [
                    'link_id' => $paymentLinkId,
                    'status' => $data['data']['attributes']['status']
                ]);
                return response()->json($data['data']);
            } else {
                Log::error('❌ Failed to get payment status: ' . $response->body());
                return response()->json([
                    'message' => 'Failed to get payment status',
                    'error' => $response->json()
                ], 400);
            }

        } catch (\Exception $e) {
            Log::error('💥 Payment status check error: ' . $e->getMessage());
            return response()->json([
                'message' => 'Payment status check failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create payment intent (legacy method, kept for compatibility)
     */
    public function createPaymentIntent(Request $request)
    {
        try {
            Log::info('🔍 Creating Payment Intent via PayMongo');

            $validated = $request->validate([
                'amount' => 'required|numeric|min:1',
                'description' => 'required|string',
            ]);

            $payload = [
                'data' => [
                    'attributes' => [
                        'amount' => (int)($validated['amount'] * 100),
                        'payment_method_allowed' => ['gcash', 'card'],
                        'currency' => 'PHP',
                        'description' => $validated['description'],
                    ]
                ]
            ];

            $response = Http::withHeaders([
                'Authorization' => 'Basic ' . base64_encode($this->secretKey . ':'),
                'Content-Type' => 'application/json',
            ])->timeout(30)
              ->post($this->baseUrl . '/payment_intents', $payload);

            Log::info('🔍 Payment Intent Response Status: ' . $response->status());
            
            if ($response->successful()) {
                $data = $response->json();
                Log::info('✅ Payment Intent Created: ' . $data['data']['id']);
                return response()->json($data['data']);
            } else {
                Log::error('❌ Payment Intent Creation Failed: ' . $response->body());
                return response()->json([
                    'message' => 'Failed to create payment intent',
                    'error' => $response->json()
                ], 400);
            }

        } catch (\Exception $e) {
            Log::error('💥 Payment Intent Creation Error: ' . $e->getMessage());
            return response()->json([
                'message' => 'Payment intent creation failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}