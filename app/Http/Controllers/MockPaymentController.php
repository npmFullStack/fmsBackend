<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Models\Booking;
use App\Models\AccountsReceivable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MockPaymentController extends Controller
{
    /**
     * Create mock payment for booking
     */
    public function createMockPayment(Request $request, $bookingId)
    {
        DB::beginTransaction();

        try {
            Log::info('💰 MOCK PAYMENT CREATION STARTED', $request->all());

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
                'payment_method' => $request->payment_method ?? 'gcash',
                'amount' => $paymentAmount,
                'status' => 'processing',
                'payment_date' => now(),
            ]);

            Log::info('💰 Mock payment record created', ['payment_id' => $payment->id]);

            // Generate mock checkout URL
            $mockCheckoutUrl = url("/mock-checkout/{$payment->id}");

            $payment->update([
                'paymongo_payment_id' => 'mock_' . $payment->id,
                'paymongo_checkout_url' => $mockCheckoutUrl,
                'paymongo_response' => ['mock' => true],
            ]);

            DB::commit();

            return response()->json([
                'message' => 'Mock payment checkout created successfully',
                'checkout_url' => $mockCheckoutUrl,
                'payment_id' => $payment->id
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('💥 MOCK PAYMENT CREATION FAILED: ' . $e->getMessage());
            return response()->json([
                'message' => 'Failed to create mock payment',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Mock checkout page
     */
    public function mockCheckout($paymentId)
    {
        $payment = Payment::with(['booking', 'user'])->find($paymentId);
        
        if (!$payment) {
            abort(404, 'Payment not found');
        }

        return view('mock-checkout', compact('payment'));
    }

    /**
     * Process mock payment
     */
    public function processMockPayment(Request $request, $paymentId)
    {
        DB::beginTransaction();

        try {
            $payment = Payment::find($paymentId);
            
            if (!$payment) {
                return response()->json(['message' => 'Payment not found'], 404);
            }

            $action = $request->action; // 'success' or 'fail'

            if ($action === 'success') {
                // Mark as paid
                $payment->update([
                    'status' => 'paid',
                    'paid_at' => now(),
                    'payment_date' => now(),
                ]);

                // Update Accounts Receivable
                $ar = AccountsReceivable::where('booking_id', $payment->booking_id)->first();
                if ($ar) {
                    $ar->update([
                        'collectible_amount' => 0,
                        'is_paid' => true
                    ]);
                }

                Log::info('✅ Mock payment marked as PAID', ['payment_id' => $payment->id]);

                return response()->json([
                    'success' => true,
                    'message' => 'Payment successful!',
                    'redirect_url' => url('/customer/bookings')
                ]);

            } else {
                // Mark as failed
                $payment->update([
                    'status' => 'failed'
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Payment failed. Please try again.'
                ]);
            }

            DB::commit();

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('💥 Mock payment processing error: ' . $e->getMessage());
            return response()->json(['message' => 'Payment processing failed'], 500);
        }
    }

    /**
     * Mock webhook simulation
     */
    public function mockWebhook(Request $request, $paymentId)
    {
        try {
            $payment = Payment::find($paymentId);
            
            if (!$payment) {
                return response()->json(['message' => 'Payment not found'], 404);
            }

            // Simulate webhook call to update payment status
            $payment->update([
                'status' => 'paid',
                'paid_at' => now(),
            ]);

            Log::info('✅ Mock webhook processed', ['payment_id' => $payment->id]);

            return response()->json(['status' => 'success']);

        } catch (\Exception $e) {
            Log::error('💥 Mock webhook error: ' . $e->getMessage());
            return response()->json(['error' => 'Webhook processing failed'], 500);
        }
    }
}