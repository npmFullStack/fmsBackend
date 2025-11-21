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
            'booking.destination'
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
                    'is_paid' => false,
                ]);
            } else {
                // Create new record
                $ar = AccountsReceivable::create([
                    'booking_id' => $validated['booking_id'],
                    'total_expenses' => $totalExpenses,
                    'total_payment' => $validated['total_payment'],
                    'is_paid' => false,
                    'is_deleted' => false,
                ]);
            }

            DB::commit();

            return response()->json([
                'message' => 'Accounts receivable record created successfully',
                'accounts_receivable' => $ar->load(['booking', 'booking.containerSize', 'booking.origin', 'booking.destination'])
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to create accounts receivable record',
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
            'is_paid' => 'sometimes|boolean',
        ]);

        $ar->update($validated);

        return response()->json($ar);
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
                SUM(total_payment) as total_gross_income,
                SUM(total_expenses) as total_expenses,
                SUM(net_revenue) as total_net_revenue,
                SUM(profit) as total_profit,
                SUM(collectible_amount) as total_collectible,
                SUM(CASE WHEN is_overdue = true THEN 1 ELSE 0 END) as total_overdue,
                SUM(CASE WHEN is_overdue = true THEN collectible_amount ELSE 0 END) as total_overdue_amount
            ')
            ->first();

        // Aging breakdown
        $agingBreakdown = AccountsReceivable::notDeleted()
            ->selectRaw('aging_bucket, COUNT(*) as count, SUM(collectible_amount) as amount')
            ->groupBy('aging_bucket')
            ->get();

        return response()->json([
            'summary' => $summary,
            'aging_breakdown' => $agingBreakdown
        ]);
    }
}