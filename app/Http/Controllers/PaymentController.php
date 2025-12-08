<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Models\Booking;
use App\Models\AccountsReceivable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;

class PaymentController extends Controller
{
    public function index(Request $request)
    {
        $perPage = $request->get('per_page', 10);
        $search = $request->get('search', '');
        $status = $request->get('status', 'all'); // all, pending, verified, approved, rejected
        $paymentMethod = $request->get('payment_method', 'all'); // all, cod, gcash

        $query = Payment::with(['booking', 'user']);

        // Filter by status
        if ($status !== 'all') {
            $query->where('status', $status);
        }

        // Filter by payment method
        if ($paymentMethod !== 'all') {
            $query->where('payment_method', $paymentMethod);
        }

        // Search
        if (!empty($search)) {
            $query->where(function($q) use ($search) {
                $q->where('reference_number', 'like', '%' . $search . '%')
                  ->orWhere('amount', 'like', '%' . $search . '%')
                  ->orWhereHas('booking', function($q) use ($search) {
                      $q->where('booking_number', 'like', '%' . $search . '%');
                  })
                  ->orWhereHas('user', function($q) use ($search) {
                      $q->where('first_name', 'like', '%' . $search . '%')
                        ->orWhere('last_name', 'like', '%' . $search . '%')
                        ->orWhere('email', 'like', '%' . $search . '%');
                  });
            });
        }

        $sort = $request->get('sort', 'created_at');
        $direction = $request->get('direction', 'desc');

        $data = $query->orderBy($sort, $direction)->paginate($perPage);

        return response()->json($data);
    }

    public function store(Request $request)
{
    \Log::info('Payment submission started', [
        'user_id' => auth()->id(),
        'ip' => $request->ip(),
        'user_agent' => $request->userAgent()
    ]);

    // Check authentication
    if (!auth()->check()) {
        \Log::warning('Unauthorized payment attempt');
        return response()->json(['message' => 'Unauthorized'], 401);
    }

    DB::beginTransaction();

    try {
        \Log::info('Validating payment request data');
        
        $validated = $request->validate([
            'booking_id' => 'required|exists:bookings,id',
            'payment_method' => 'required|in:cod,gcash',
            'amount' => 'required|numeric|min:0.01',
            'reference_number' => 'nullable|string|max:255',
            'gcash_receipt_image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        \Log::info('Validation passed', $validated);

        // Get the booking
        $booking = Booking::notDeleted()->with(['accountsReceivable'])->find($validated['booking_id']);
        
        if (!$booking) {
            \Log::error('Booking not found', ['booking_id' => $validated['booking_id']]);
            return response()->json(['message' => 'Booking not found'], 404);
        }

        \Log::info('Booking found', [
            'booking_id' => $booking->id,
            'booking_number' => $booking->booking_number,
            'status' => $booking->status
        ]);

        // Check if booking is approved
        if ($booking->status !== 'approved') {
            \Log::error('Booking not approved', ['booking_status' => $booking->status]);
            return response()->json([
                'message' => 'Booking must be approved before making payments',
                'booking_status' => $booking->status
            ], 400);
        }

        // Check if booking already has AR
        $ar = $booking->accountsReceivable;
        
        if (!$ar) {
            \Log::error('No AR record found for booking', ['booking_id' => $validated['booking_id']]);
            return response()->json([
                'message' => 'No payment amount set for this booking. Please contact admin.'
            ], 400);
        }

        \Log::info('AR record found', [
            'ar_id' => $ar->id,
            'total_payment' => $ar->total_payment,
            'paid_amount' => $ar->paid_amount,
            'collectible_amount' => $ar->collectible_amount,
            'is_paid' => $ar->is_paid,
            'current_payment_method' => $ar->payment_method
        ]);

        // Check if booking is already fully paid
        if ($ar->is_paid || $ar->collectible_amount <= 0) {
            \Log::warning('Booking already paid', [
                'collectible_amount' => $ar->collectible_amount,
                'is_paid' => $ar->is_paid
            ]);
            return response()->json([
                'message' => 'This booking is already fully paid',
                'collectible_amount' => $ar->collectible_amount
            ], 400);
        }

        // Check if payment amount exceeds collectible amount
        $paymentAmount = floatval($validated['amount']);
        $collectibleAmount = floatval($ar->collectible_amount);
        
        \Log::info('Checking payment amount', [
            'payment_amount' => $paymentAmount,
            'collectible_amount' => $collectibleAmount
        ]);

        if ($paymentAmount > $collectibleAmount) {
            return response()->json([
                'message' => 'Payment amount cannot exceed the collectible amount',
                'collectible_amount' => $collectibleAmount,
                'payment_amount' => $paymentAmount,
                'excess' => $paymentAmount - $collectibleAmount
            ], 400);
        }

        // Handle file upload if payment method is gcash
        $gcashReceiptPath = null;
        if ($validated['payment_method'] === 'gcash' && $request->hasFile('gcash_receipt_image')) {
            \Log::info('Processing GCash receipt upload');
            try {
                $gcashReceiptPath = $request->file('gcash_receipt_image')->store('payments/receipts', 'public');
                \Log::info('GCash receipt uploaded successfully', ['path' => $gcashReceiptPath]);
            } catch (\Exception $e) {
                \Log::error('Failed to upload GCash receipt', ['error' => $e->getMessage()]);
                return response()->json([
                    'message' => 'Failed to upload receipt image',
                    'error' => $e->getMessage()
                ], 500);
            }
        } elseif ($validated['payment_method'] === 'gcash' && !$request->hasFile('gcash_receipt_image')) {
            \Log::warning('GCash payment without receipt');
            return response()->json([
                'message' => 'GCash payments require a receipt image'
            ], 400);
        }

        // Calculate new values for AR
        $newPaidAmount = $ar->paid_amount + $paymentAmount;
        $newCollectibleAmount = $collectibleAmount - $paymentAmount;
        $isNowPaid = $newCollectibleAmount <= 0;
        
        // Always update payment method in AR when making a payment
        // This ensures the payment method is saved even for partial payments
        $ar->update([
            'paid_amount' => $newPaidAmount,
            'collectible_amount' => $newCollectibleAmount,
            'payment_method' => $validated['payment_method'], // This saves payment method to AR
            'is_paid' => $isNowPaid,
            'payment_date' => now(),
        ]);

        \Log::info('AR updated for payment', [
            'payment_method' => $validated['payment_method'],
            'old_paid_amount' => $ar->paid_amount,
            'new_paid_amount' => $newPaidAmount,
            'new_collectible_amount' => $newCollectibleAmount,
            'is_paid' => $isNowPaid
        ]);

        // Create payment record
        \Log::info('Creating payment record');
        $paymentData = [
            'booking_id' => $validated['booking_id'],
            'user_id' => auth()->id(),
            'payment_method' => $validated['payment_method'],
            'gcash_receipt_image' => $gcashReceiptPath,
            'reference_number' => $validated['reference_number'] ?? null,
            'amount' => $paymentAmount,
            'status' => 'pending',
            'payment_date' => now(),
        ];

        \Log::info('Payment data to create', $paymentData);
        
        $payment = Payment::create($paymentData);
        
        if (!$payment) {
            throw new \Exception('Failed to create payment record');
        }

        \Log::info('Payment created successfully', ['payment_id' => $payment->id]);

        // If payment method is COD, mark as verified automatically
        if ($validated['payment_method'] === 'cod') {
            \Log::info('COD payment detected, marking as verified');
            $payment->markAsVerified('COD payment - auto-verified on submission');
        }

        DB::commit();
        
        \Log::info('Payment transaction completed successfully', [
            'payment_id' => $payment->id,
            'booking_id' => $booking->id,
            'amount' => $paymentAmount,
            'method' => $validated['payment_method'],
            'ar_payment_method' => $ar->payment_method, // Log the saved payment method
            'ar_updated' => $ar->wasChanged() // Check if AR was actually updated
        ]);

        // Load relationships for response
        $payment->load(['booking', 'user']);
        $ar->refresh(); // Get fresh AR data

        return response()->json([
            'message' => $validated['payment_method'] === 'cod' 
                ? 'COD payment recorded successfully! Payment will be collected upon delivery.' 
                : 'Payment submitted successfully! Please wait for admin verification.',
            'payment' => $payment,
            'accounts_receivable' => $ar,
            'remaining_balance' => $newCollectibleAmount,
            'payment_method_set' => $ar->payment_method,
            'is_paid' => $isNowPaid,
            'payment_method' => $validated['payment_method']
        ], 201);

    } catch (\Illuminate\Validation\ValidationException $e) {
        DB::rollBack();
        \Log::error('Payment validation failed', [
            'errors' => $e->errors(),
            'request' => $request->all()
        ]);
        return response()->json([
            'message' => 'Validation failed',
            'errors' => $e->errors()
        ], 422);

    } catch (\Exception $e) {
        DB::rollBack();
        \Log::error('Payment submission failed', [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
            'request_data' => $request->except(['gcash_receipt_image'])
        ]);
        
        return response()->json([
            'message' => 'Failed to submit payment',
            'error' => env('APP_DEBUG') ? $e->getMessage() : 'Internal server error'
        ], 500);
    }
}

    public function show($id)
    {
        $payment = Payment::with(['booking', 'user'])->find($id);

        if (!$payment) {
            return response()->json(['message' => 'Payment not found'], 404);
        }

        return response()->json($payment);
    }

    public function updateStatus(Request $request, $id)
    {
        DB::beginTransaction();

        try {
            $validated = $request->validate([
                'status' => 'required|in:verified,approved,rejected',
                'admin_notes' => 'nullable|string'
            ]);

            $payment = Payment::with(['booking', 'booking.accountsReceivable'])->find($id);

            if (!$payment) {
                return response()->json(['message' => 'Payment not found'], 404);
            }

            // Update payment status
            $payment->update([
                'status' => $validated['status'],
                'admin_notes' => $validated['admin_notes'] ?? null
            ]);

            // If payment is approved, update the AR record
            if ($validated['status'] === 'approved') {
                $ar = $payment->booking->accountsReceivable;
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
                }
            }

            DB::commit();

            return response()->json([
                'message' => 'Payment status updated successfully',
                'payment' => $payment->fresh(['booking', 'booking.accountsReceivable'])
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to update payment status',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function destroy($id)
    {
        $payment = Payment::find($id);

        if (!$payment) {
            return response()->json(['message' => 'Payment not found'], 404);
        }

        // Delete receipt image if exists
        if ($payment->gcash_receipt_image) {
            Storage::disk('public')->delete($payment->gcash_receipt_image);
        }

        $payment->delete();

        return response()->json(['message' => 'Payment deleted successfully']);
    }

    // Get payments by booking
    public function getByBooking($bookingId)
    {
        $payments = Payment::with(['user'])
            ->where('booking_id', $bookingId)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($payments);
    }

    // Get customer's payments
    public function getCustomerPayments(Request $request)
    {
        $user = auth()->user();
        $perPage = $request->get('per_page', 10);

        $payments = Payment::with(['booking'])
            ->where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);

        return response()->json($payments);
    }
}