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
     * Create a payment intent for Paymongo
     */
    private function createPaymongoPaymentIntent(Payment $payment)
{
    try {
        Log::info('=== PAYMONGO PAYMENT INTENT CREATION ===');
        Log::info('Using Secret Key: ' . substr($this->paymongoSecret, 0, 10) . '...');
        Log::info('Amount: ' . $payment->amount . ' PHP (' . ($payment->amount * 100) . ' cents)');

        // Simple payload without complex options
        $payload = [
            'data' => [
                'attributes' => [
                    'amount' => (int)($payment->amount * 100),
                    'payment_method_allowed' => ['gcash'],
                    'currency' => 'PHP',
                    'description' => 'Payment for Booking #' . $payment->booking->booking_number,
                ]
            ]
        ];

        $response = Http::withHeaders([
            'Authorization' => 'Basic ' . base64_encode($this->paymongoSecret . ':'),
            'Content-Type' => 'application/json',
        ])->timeout(30)
          ->post($this->paymongoUrl . '/payment_intents', $payload);

        Log::info('PayMongo Response Status: ' . $response->status());
        
        if ($response->successful()) {
            $data = $response->json();
            Log::info('Payment Intent Created: ' . $data['data']['id']);
            return $data['data'];
        } else {
            $errorBody = $response->body();
            Log::error('PayMongo API Error: ' . $errorBody);
            
            // Check for specific errors
            if ($response->status() === 401) {
                throw new \Exception('Invalid PayMongo API key');
            } elseif ($response->status() === 402) {
                throw new \Exception('Payment processing not allowed');
            } else {
                throw new \Exception('PayMongo API error: ' . $errorBody);
            }
        }

    } catch (\Exception $e) {
        Log::error('PayMongo Exception: ' . $e->getMessage());
        throw new \Exception('PayMongo service error: ' . $e->getMessage());
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
            
            Log::info('Webhook Event Type: ' . $eventType);

            if ($eventType === 'payment_intent.succeeded') {
                $this->handlePaymentSucceeded($data);
            } elseif ($eventType === 'payment_intent.payment_failed') {
                $this->handlePaymentFailed($data);
            } elseif ($eventType === 'payment_intent.canceled') {
                $this->handlePaymentCanceled($data);
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
    $webhookSecret = config('services.paymongo.webhook_secret');
    
    // For development, skip verification
    if (app()->environment('local') || app()->environment('development')) {
        Log::info('Webhook verification skipped for development');
        return true;
    }
    
    if (!$webhookSecret) {
        Log::warning('Paymongo webhook secret not set');
        return false;
    }

    $computedSignature = hash_hmac('sha256', $payload, $webhookSecret);
    
    return hash_equals($signature, $computedSignature);
}

    /**
     * Handle successful payment
     */
    private function handlePaymentSucceeded($data)
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
                'paymongo_status' => 'succeeded',
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

                // Recalculate financials
                $ar->calculateFinancials()->save();

                // Send payment confirmation email
                $this->sendPaymentConfirmation($ar, $payment);
            }

            Log::info('Payment marked as completed', ['payment_id' => $payment->id]);
        } else {
            Log::error('Payment not found for intent: ' . $paymentIntentId);
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
                    'paymongo_status' => 'failed',
                    'paymongo_response' => $data,
                ]);
                
                Log::info('Payment marked as failed', ['payment_id' => $payment->id]);
            }
        }
    }

    /**
     * Handle canceled payment
     */
    private function handlePaymentCanceled($data)
    {
        $paymentIntentId = $data['data']['attributes']['data']['id'] ?? null;
        
        if ($paymentIntentId) {
            $payment = Payment::where('paymongo_payment_intent_id', $paymentIntentId)->first();
            
            if ($payment) {
                $payment->update([
                    'status' => 'cancelled',
                    'paymongo_status' => 'canceled',
                    'paymongo_response' => $data,
                ]);
                
                Log::info('Payment marked as cancelled', ['payment_id' => $payment->id]);
            }
        }
    }

    private function sendPaymentConfirmation($ar, $payment)
    {
        // Implement email sending logic here
        // You can use similar logic as in AccountsReceivableController
        try {
            $booking = $ar->booking;
            $user = $booking->user;
            
            // You'll need to create this Mailable
            // Mail::to($user->email)->send(new \App\Mail\PaymentConfirmation($ar, $payment));
            
            Log::info('Payment confirmation email would be sent to: ' . $user->email, [
                'payment_id' => $payment->id,
                'booking_id' => $booking->id
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to send payment confirmation: ' . $e->getMessage());
        }
    }

    /**
     * Get payment intent status
     */
    public function getPaymentStatus($paymentIntentId)
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Basic ' . base64_encode($this->secretKey . ':'),
            ])->get($this->baseUrl . '/payment_intents/' . $paymentIntentId);

            if ($response->successful()) {
                $data = $response->json();
                return response()->json($data['data']);
            } else {
                return response()->json([
                    'message' => 'Failed to get payment status',
                    'error' => $response->json()
                ], 400);
            }

        } catch (\Exception $e) {
            Log::error('Paymongo status check error: ' . $e->getMessage());
            return response()->json([
                'message' => 'Payment status check failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}