<?php
// [file name]: PaymentController.php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Models\Booking;
use App\Models\AccountsReceivable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PaymentController extends Controller
{
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
            $validated = $request->validate([
                'booking_id' => 'required|exists:bookings,id',
                'payment_method' => 'required|in:gcash,paymongo',
                'amount' => 'required|numeric|min:0',
                'gcash_mobile_number' => 'required_if:payment_method,gcash|string|max:20',
            ]);

            // Get authenticated user
            $user = auth()->user();
            $booking = Booking::notDeleted()->find($validated['booking_id']);

            if (!$booking) {
                return response()->json(['message' => 'Booking not found'], 404);
            }

            // Check if user owns the booking
            if ($booking->user_id !== $user->id) {
                return response()->json(['message' => 'Unauthorized access to this booking'], 403);
            }

            // Check if booking has AR record
            $ar = AccountsReceivable::where('booking_id', $booking->id)->first();
            if (!$ar) {
                return response()->json(['message' => 'No accounts receivable record found for this booking'], 404);
            }

            // Check if amount is valid
            if ($validated['amount'] > $ar->collectible_amount) {
                return response()->json([
                    'message' => 'Payment amount exceeds collectible amount',
                    'collectible_amount' => $ar->collectible_amount
                ], 400);
            }

            // Create payment
            $payment = Payment::create([
                'booking_id' => $validated['booking_id'],
                'user_id' => $user->id,
                'payment_method' => $validated['payment_method'],
                'amount' => $validated['amount'],
                'status' => 'pending',
                'gcash_mobile_number' => $validated['gcash_mobile_number'] ?? null,
            ]);

            DB::commit();

            return response()->json([
                'message' => 'Payment created successfully',
                'payment' => $payment->load(['booking', 'user'])
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to create payment',
                'error' => $e->getMessage()
            ], 500);
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
                $ar = AccountsReceivable::where('booking_id', $payment->booking_id)->first();
                if ($ar) {
                    // Update AR with payment
                    $ar->update([
                        'total_payment' => $ar->total_payment + $payment->amount,
                        'collectible_amount' => max(0, $ar->collectible_amount - $payment->amount),
                        'is_paid' => ($ar->collectible_amount - $payment->amount) <= 0
                    ]);
                }
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
}