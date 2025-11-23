<?php

namespace App\Http\Controllers;

use App\Models\AccountsReceivable;
use App\Models\AccountsPayable;
use App\Models\Booking;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class AccountsReceivableController extends Controller
{
    public function index(Request $request)
    {
        $perPage = $request->get('per_page', 10);
        $search = $request->get('search', '');
        $status = $request->get('status', 'all'); // all, paid, unpaid, overdue
        $aging = $request->get('aging', 'all'); // all, current, 1-30, etc.

        $query = AccountsReceivable::with([
            'booking' => function($query) {
                $query->notDeleted();
            },
            'booking.containerSize',
            'booking.origin',
            'booking.destination',
            'payments' // Load payments
        ])->notDeleted();

        // Filter by payment status
        if ($status === 'paid') {
            $query->paid();
        } elseif ($status === 'unpaid') {
            $query->unpaid();
        } elseif ($status === 'overdue') {
            $query->unpaid()->overdue();
        }

        // Filter by aging bucket
        if ($aging !== 'all') {
            $query->byAgingBucket($aging);
        }

        // Search
        if (!empty($search)) {
            $query->where(function($q) use ($search) {
                $q->where('collectible_amount', 'like', '%' . $search . '%')
                  ->orWhereHas('booking', function($q) use ($search) {
                      $q->where('booking_number', 'like', '%' . $search . '%')
                        ->orWhere('first_name', 'like', '%' . $search . '%')
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
            'total_payment' => 'required|numeric|min:0',
            'charges' => 'required|array', // ✅ Added validation for charges
            'charges.*.description' => 'required|string',
            'charges.*.type' => 'required|string',
            'charges.*.amount' => 'required|numeric|min:0',
            'charges.*.markup' => 'required|numeric|min:0',
            'charges.*.markup_amount' => 'required|numeric|min:0',
            'charges.*.total' => 'required|numeric|min:0',
        ]);

        $booking = Booking::notDeleted()->find($validated['booking_id']);
        
        if (!$booking) {
            return response()->json(['message' => 'Booking not found'], 404);
        }

        // Get the booking's total expenses from AP
        $ap = AccountsPayable::where('booking_id', $validated['booking_id'])->notDeleted()->first();
        $totalExpenses = $ap ? $ap->total_expenses : 0;

        // Check if AR record already exists
        $ar = AccountsReceivable::where('booking_id', $validated['booking_id'])->first();

        if ($ar) {
            // Update existing record
            $ar->update([
                'total_expenses' => $totalExpenses,
                'total_payment' => $validated['total_payment'],
                'charges' => $validated['charges'], // ✅ Save charges array
                'is_paid' => false,
            ]);
        } else {
            // Create new record
            $ar = AccountsReceivable::create([
                'booking_id' => $validated['booking_id'],
                'total_expenses' => $totalExpenses,
                'total_payment' => $validated['total_payment'],
                'charges' => $validated['charges'], // ✅ Save charges array
                'collectible_amount' => $validated['total_payment'],
                'is_paid' => false,
                'is_deleted' => false,
            ]);
        }

        // Calculate financials
        $ar->calculateFinancials();
        $ar->save();

        DB::commit();

        return response()->json([
            'message' => 'Payment amount set successfully',
            'accounts_receivable' => $ar->load(['booking', 'booking.containerSize', 'booking.origin', 'booking.destination'])
        ], 200);

    } catch (\Exception $e) {
        DB::rollBack();
        return response()->json([
            'message' => 'Failed to set payment amount',
            'error' => $e->getMessage()
        ], 500);
    }
}

    // NEW METHOD: Process payment from customer
    public function processPayment(Request $request, $id)
    {
        DB::beginTransaction();

        try {
            $validated = $request->validate([
                'amount' => 'required|numeric|min:0',
                'payment_method' => 'required|in:gcash,paymongo,bank_transfer',
                'reference_number' => 'nullable|string|max:255',
                'payment_date' => 'required|date',
            ]);

            $ar = AccountsReceivable::notDeleted()->find($id);

            if (!$ar) {
                return response()->json(['message' => 'Accounts receivable record not found'], 404);
            }

            // Create payment record
            $payment = Payment::create([
                'booking_id' => $ar->booking_id,
                'user_id' => $ar->booking->user_id,
                'amount' => $validated['amount'],
                'payment_method' => $validated['payment_method'],
                'reference_number' => $validated['reference_number'],
                'status' => 'completed',
                'payment_date' => $validated['payment_date'],
            ]);

            // Update AR record
            $remainingAmount = $ar->collectible_amount - $validated['amount'];
            
            if ($remainingAmount <= 0) {
                $ar->markAsPaid();
            } else {
                $ar->update([
                    'collectible_amount' => $remainingAmount,
                    'is_paid' => false,
                ]);
            }

            // Send payment confirmation email
            $this->sendPaymentConfirmationEmail($ar, $payment);

            DB::commit();

            return response()->json([
                'message' => 'Payment processed successfully',
                'accounts_receivable' => $ar->fresh(['payments', 'booking']),
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

    // NEW METHOD: Get payment breakdown
    public function getPaymentBreakdown($id)
    {
        $ar = AccountsReceivable::with([
            'booking',
            'payments' => function($query) {
                $query->orderBy('created_at', 'desc');
            }
        ])->notDeleted()->find($id);

        if (!$ar) {
            return response()->json(['message' => 'Accounts receivable record not found'], 404);
        }

        $breakdown = [
            'total_payment' => $ar->total_payment,
            'total_expenses' => $ar->total_expenses,
            'collectible_amount' => $ar->collectible_amount,
            'paid_amount' => $ar->total_payment - $ar->collectible_amount,
            'payments' => $ar->payments,
            'profit' => $ar->profit,
            'net_revenue' => $ar->net_revenue,
        ];

        return response()->json($breakdown);
    }

    private function sendPaymentConfirmationEmail($ar, $payment)
    {
        try {
            $user = $ar->booking->user;
            $booking = $ar->booking;
            
            // You'll need to create this Mailable
            Mail::to($user->email)->send(new \App\Mail\PaymentConfirmation($ar, $payment));
            
            \Log::info('Payment confirmation email sent', [
                'user_id' => $user->id,
                'ar_id' => $ar->id,
                'payment_id' => $payment->id
            ]);
        } catch (\Exception $e) {
            \Log::error('Failed to send payment confirmation email: ' . $e->getMessage());
            // Don't throw error, just log it
        }
    }
    
    public function sendPaymentEmail($id)
{
    try {
        $ar = AccountsReceivable::with([
            'booking.user',
            'booking.containerSize',
            'booking.origin',
            'booking.destination'
        ])->notDeleted()->find($id);

        if (!$ar) {
            return response()->json(['message' => 'Accounts receivable record not found'], 404);
        }

        // Check if payment amount is set
        if (!$ar->total_payment || $ar->total_payment <= 0) {
            return response()->json(['message' => 'No payment amount set for this booking'], 400);
        }

        $user = $ar->booking->user;
        
        if (!$user || !$user->email) {
            return response()->json(['message' => 'Customer email not found'], 400);
        }

        // Send payment request email
        Mail::to($user->email)->send(new \App\Mail\PaymentRequest($ar));

        \Log::info('Payment request email sent successfully', [
            'user_id' => $user->id,
            'ar_id' => $ar->id,
            'email' => $user->email,
            'amount' => $ar->total_payment
        ]);

        return response()->json([
            'message' => 'Payment request email sent successfully',
            'email_sent_to' => $user->email
        ]);

    } catch (\Exception $e) {
        \Log::error('Failed to send payment request email: ' . $e->getMessage());
        return response()->json([
            'message' => 'Failed to send payment request email',
            'error' => $e->getMessage()
        ], 500);
    }
}

    public function show($id)
    {
        $ar = AccountsReceivable::with([
            'booking' => function($query) {
                $query->notDeleted();
            },
            'booking.containerSize',
            'booking.origin',
            'booking.destination',
            'booking.shippingLine',
            'booking.truckComp'
        ])->notDeleted()->find($id);

        if (!$ar) {
            return response()->json(['message' => 'Accounts receivable record not found'], 404);
        }

        return response()->json($ar);
    }

    public function update(Request $request, $id)
{
    $ar = AccountsReceivable::notDeleted()->find($id);

    if (!$ar) {
        return response()->json(['message' => 'Accounts receivable record not found'], 404);
    }

    $validated = $request->validate([
        'total_payment' => 'sometimes|numeric|min:0',
        'charges' => 'sometimes|array', // ✅ Added charges validation
        'is_paid' => 'sometimes|boolean',
    ]);

    // If updating total_payment, also update charges if provided
    if (isset($validated['total_payment']) && isset($validated['charges'])) {
        $ar->update([
            'total_payment' => $validated['total_payment'],
            'charges' => $validated['charges'], // ✅ Save charges
        ]);
    } elseif (isset($validated['total_payment'])) {
        $ar->update(['total_payment' => $validated['total_payment']]);
    } elseif (isset($validated['charges'])) {
        $ar->update(['charges' => $validated['charges']]); // ✅ Save charges only
    }

    if (isset($validated['is_paid'])) {
        $ar->update(['is_paid' => $validated['is_paid']]);
    }

    // Recalculate financials
    $ar->calculateFinancials();
    $ar->save();

    return response()->json($ar->fresh());
}

    public function destroy($id)
    {
        $ar = AccountsReceivable::notDeleted()->find($id);

        if (!$ar) {
            return response()->json(['message' => 'Accounts receivable record not found'], 404);
        }

        $ar->update(['is_deleted' => true]);

        return response()->json(['message' => 'Accounts receivable record deleted successfully'], 200);
    }

    public function getByBooking($bookingId)
    {
        $ar = AccountsReceivable::with([
            'booking' => function($query) {
                $query->notDeleted();
            },
            'booking.containerSize',
            'booking.origin',
            'booking.destination'
        ])->where('booking_id', $bookingId)->notDeleted()->first();

        if (!$ar) {
            return response()->json(['message' => 'No accounts receivable record found for this booking'], 404);
        }

        return response()->json($ar);
    }

    public function markAsPaid($id)
    {
        $ar = AccountsReceivable::notDeleted()->find($id);

        if (!$ar) {
            return response()->json(['message' => 'Accounts receivable record not found'], 404);
        }

        $ar->markAsPaid()->save();

        return response()->json([
            'message' => 'Accounts receivable marked as paid successfully',
            'accounts_receivable' => $ar
        ]);
    }

    // Update AR records when booking status changes to delivered
    public function updateOnDelivery($bookingId)
    {
        DB::beginTransaction();

        try {
            $booking = Booking::notDeleted()->find($bookingId);
            
            if (!$booking || $booking->booking_status !== 'delivered') {
                return response()->json(['message' => 'Booking not found or not delivered'], 404);
            }

            $ar = AccountsReceivable::where('booking_id', $bookingId)->first();
            
            if ($ar) {
                $ar->setInvoiceDates()->calculateAging()->save();
            }

            DB::commit();

            return response()->json([
                'message' => 'AR record updated for delivered booking',
                'accounts_receivable' => $ar
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to update AR record',
                'error' => $e->getMessage()
            ], 500);
        }
    }

// Get financial summary
public function getFinancialSummary()
{
    $summary = AccountsReceivable::notDeleted()
        ->selectRaw('
            COUNT(*) as total_records,
            COALESCE(SUM(total_payment), 0) as total_gross_income,
            COALESCE(SUM(total_expenses), 0) as total_expenses,
            COALESCE(SUM(net_revenue), 0) as total_net_revenue,
            COALESCE(SUM(profit), 0) as total_profit,
            COALESCE(SUM(collectible_amount), 0) as total_collectible,
            SUM(CASE WHEN is_overdue = true THEN 1 ELSE 0 END) as total_overdue,
            COALESCE(SUM(CASE WHEN is_overdue = true THEN collectible_amount ELSE 0 END), 0) as total_overdue_amount
        ')
        ->first();

    // Aging breakdown
    $agingBreakdown = AccountsReceivable::notDeleted()
        ->selectRaw('aging_bucket, COUNT(*) as count, COALESCE(SUM(collectible_amount), 0) as amount')
        ->groupBy('aging_bucket')
        ->get();

    return response()->json([
        'summary' => $summary,
        'aging_breakdown' => $agingBreakdown
    ]);
}
}