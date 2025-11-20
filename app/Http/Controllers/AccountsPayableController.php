<?php
// [file name]: AccountsPayableController.php

namespace App\Http\Controllers;

use App\Models\AccountsPayable;
use App\Models\Booking;
use App\Models\ApFreightCharge;
use App\Models\ApTruckingCharge;
use App\Models\ApPortCharge;
use App\Models\ApMiscCharge;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AccountsPayableController extends Controller
{
    public function index(Request $request)
    {
        $perPage = $request->get('per_page', 10);
        $search = $request->get('search', '');

        $query = AccountsPayable::with([
            'booking',
            'booking.containerSize',
            'booking.origin',
            'booking.destination',
            'freightCharge',
            'truckingCharges',
            'portCharges',
            'miscCharges'
        ])->notDeleted();

        if (!empty($search)) {
            $query->where(function($q) use ($search) {
                $q->where('total_expenses', 'like', '%' . $search . '%')
                  ->orWhereHas('booking', function($q) use ($search) {
                      $q->where('booking_number', 'like', '%' . $search . '%')
                        ->orWhere('first_name', 'like', '%' . $search . '%')
                        ->orWhere('last_name', 'like', '%' . $search . '%');
                  })
                  ->orWhereHas('freightCharge', function($q) use ($search) {
                      $q->where('voucher_number', 'like', '%' . $search . '%');
                  })
                  ->orWhereHas('truckingCharges', function($q) use ($search) {
                      $q->where('voucher_number', 'like', '%' . $search . '%');
                  })
                  ->orWhereHas('portCharges', function($q) use ($search) {
                      $q->where('voucher_number', 'like', '%' . $search . '%');
                  })
                  ->orWhereHas('miscCharges', function($q) use ($search) {
                      $q->where('voucher_number', 'like', '%' . $search . '%');
                  });
            });
        }

        // Filter by payment status
        if ($request->has('is_paid')) {
            $query->where('is_paid', $request->get('is_paid'));
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
            
            // Freight charges
            'freight_charge' => 'nullable|array',
            'freight_charge.amount' => 'nullable|numeric|min:0',
            'freight_charge.check_date' => 'nullable|date',
            
            // Trucking charges - ensure unique types in the request
            'trucking_charges' => 'nullable|array',
            'trucking_charges.*.type' => 'required|in:ORIGIN,DESTINATION',
            'trucking_charges.*.amount' => 'required|numeric|min:0',
            'trucking_charges.*.check_date' => 'nullable|date',
            
            // Port charges - ensure unique types in the request
            'port_charges' => 'nullable|array',
            'port_charges.*.charge_type' => 'required|in:CRAINAGE,ARRASTRE_ORIGIN,ARRASTRE_DEST,WHARFAGE_ORIGIN,WHARFAGE_DEST,LABOR_ORIGIN,LABOR_DEST',
            'port_charges.*.payee' => 'nullable|string|max:255',
            'port_charges.*.amount' => 'required|numeric|min:0',
            'port_charges.*.check_date' => 'nullable|date',
            
            // Misc charges - ensure unique types in the request
            'misc_charges' => 'nullable|array',
            'misc_charges.*.charge_type' => 'required|in:REBATES,STORAGE,FACILITATION,DENR',
            'misc_charges.*.payee' => 'nullable|string|max:255',
            'misc_charges.*.amount' => 'required|numeric|min:0',
            'misc_charges.*.check_date' => 'nullable|date',
        ]);

        // Custom validation to ensure no duplicate types in the same request
        if (isset($validated['trucking_charges'])) {
            $truckingTypes = array_column($validated['trucking_charges'], 'type');
            if (count($truckingTypes) !== count(array_unique($truckingTypes))) {
                throw new \Exception('Duplicate trucking charge types are not allowed in the same request.');
            }
        }

        if (isset($validated['port_charges'])) {
            $portTypes = array_column($validated['port_charges'], 'charge_type');
            if (count($portTypes) !== count(array_unique($portTypes))) {
                throw new \Exception('Duplicate port charge types are not allowed in the same request.');
            }
        }

        if (isset($validated['misc_charges'])) {
            $miscTypes = array_column($validated['misc_charges'], 'charge_type');
            if (count($miscTypes) !== count(array_unique($miscTypes))) {
                throw new \Exception('Duplicate miscellaneous charge types are not allowed in the same request.');
            }
        }

        // Find existing AP record or create new one
        $ap = AccountsPayable::where('booking_id', $validated['booking_id'])->first();

        $isNewRecord = false;
        
        if (!$ap) {
            // Create new AP record
            $ap = AccountsPayable::create([
                'booking_id' => $validated['booking_id'],
                'is_paid' => false,
                'is_deleted' => false,
                'total_expenses' => 0,
            ]);
            $isNewRecord = true;
        }

        // ADD (not replace) charges to the AP record
        $this->addChargesToAP($ap, $validated);

        // Calculate and update total expenses
        $ap->calculateTotalAmount();

        DB::commit();

        return response()->json([
            'message' => $isNewRecord ? 'Accounts payable record created successfully' : 'Additional charges added successfully',
            'accounts_payable' => $ap->load(['freightCharge', 'truckingCharges', 'portCharges', 'miscCharges', 'booking'])
        ], $isNewRecord ? 201 : 200);

    } catch (\Exception $e) {
        DB::rollBack();
        return response()->json([
            'message' => 'Failed to add charges',
            'error' => $e->getMessage()
        ], 500);
    }
}

    private function addChargesToAP($ap, $validated)
{
    // Add freight charge if provided and amount > 0
    if (isset($validated['freight_charge']) && $validated['freight_charge']['amount'] > 0) {
        $existingFreight = $ap->freightCharge;
        if (!$existingFreight) {
            // Create new freight charge if none exists
            ApFreightCharge::create([
                'ap_id' => $ap->id,
                'voucher_number' => AccountsPayable::generateChargeVoucherNumber('FRT'),
                'amount' => $validated['freight_charge']['amount'],
                'check_date' => $validated['freight_charge']['check_date'] ?? null,
                'voucher' => null,
                'is_paid' => false,
                'is_deleted' => false,
            ]);
        } else {
            // Update existing freight charge (add to existing amount)
            $existingFreight->update([
                'amount' => $existingFreight->amount + $validated['freight_charge']['amount'],
                'check_date' => $validated['freight_charge']['check_date'] ?? $existingFreight->check_date,
            ]);
        }
    }

    // Add trucking charges - UPDATE existing ones instead of creating duplicates
    if (isset($validated['trucking_charges'])) {
        foreach ($validated['trucking_charges'] as $truckingCharge) {
            if ($truckingCharge['amount'] > 0) {
                // Find existing trucking charge of the same type
                $existingTrucking = ApTruckingCharge::where('ap_id', $ap->id)
                    ->where('type', $truckingCharge['type'])
                    ->where('is_deleted', false)
                    ->first();

                if ($existingTrucking) {
                    // Update existing charge (add to existing amount)
                    $existingTrucking->update([
                        'amount' => $existingTrucking->amount + $truckingCharge['amount'],
                        'check_date' => $truckingCharge['check_date'] ?? $existingTrucking->check_date,
                    ]);
                } else {
                    // Create new trucking charge only if none exists for this type
                    ApTruckingCharge::create([
                        'ap_id' => $ap->id,
                        'voucher_number' => AccountsPayable::generateChargeVoucherNumber('TRK'),
                        'type' => $truckingCharge['type'],
                        'amount' => $truckingCharge['amount'],
                        'check_date' => $truckingCharge['check_date'] ?? null,
                        'voucher' => null,
                        'is_paid' => false,
                        'is_deleted' => false,
                    ]);
                }
            }
        }
    }

    // Add port charges - UPDATE existing ones instead of creating duplicates
    if (isset($validated['port_charges'])) {
        foreach ($validated['port_charges'] as $portCharge) {
            if ($portCharge['amount'] > 0) {
                // Find existing port charge of the same type
                $existingPort = ApPortCharge::where('ap_id', $ap->id)
                    ->where('charge_type', $portCharge['charge_type'])
                    ->where('is_deleted', false)
                    ->first();

                if ($existingPort) {
                    // Update existing charge (add to existing amount)
                    $existingPort->update([
                        'amount' => $existingPort->amount + $portCharge['amount'],
                        'payee' => $portCharge['payee'] ?? $existingPort->payee,
                        'check_date' => $portCharge['check_date'] ?? $existingPort->check_date,
                    ]);
                } else {
                    // Create new port charge only if none exists for this type
                    ApPortCharge::create([
                        'ap_id' => $ap->id,
                        'voucher_number' => AccountsPayable::generateChargeVoucherNumber('PRT'),
                        'charge_type' => $portCharge['charge_type'],
                        'payee' => $portCharge['payee'] ?? null,
                        'amount' => $portCharge['amount'],
                        'check_date' => $portCharge['check_date'] ?? null,
                        'voucher' => null,
                        'is_paid' => false,
                        'is_deleted' => false,
                    ]);
                }
            }
        }
    }

    // Add misc charges - UPDATE existing ones instead of creating duplicates
    if (isset($validated['misc_charges'])) {
        foreach ($validated['misc_charges'] as $miscCharge) {
            if ($miscCharge['amount'] > 0) {
                // Find existing misc charge of the same type
                $existingMisc = ApMiscCharge::where('ap_id', $ap->id)
                    ->where('charge_type', $miscCharge['charge_type'])
                    ->where('is_deleted', false)
                    ->first();

                if ($existingMisc) {
                    // Update existing charge (add to existing amount)
                    $existingMisc->update([
                        'amount' => $existingMisc->amount + $miscCharge['amount'],
                        'payee' => $miscCharge['payee'] ?? $existingMisc->payee,
                        'check_date' => $miscCharge['check_date'] ?? $existingMisc->check_date,
                    ]);
                } else {
                    // Create new misc charge only if none exists for this type
                    ApMiscCharge::create([
                        'ap_id' => $ap->id,
                        'voucher_number' => AccountsPayable::generateChargeVoucherNumber('MSC'),
                        'charge_type' => $miscCharge['charge_type'],
                        'payee' => $miscCharge['payee'] ?? null,
                        'amount' => $miscCharge['amount'],
                        'check_date' => $miscCharge['check_date'] ?? null,
                        'voucher' => null,
                        'is_paid' => false,
                        'is_deleted' => false,
                    ]);
                }
            }
        }
    }
}

    public function show($id)
    {
        $ap = AccountsPayable::with([
            'booking',
            'booking.containerSize',
            'booking.origin',
            'booking.destination',
            'booking.shippingLine',
            'booking.truckComp',
            'freightCharge',
            'truckingCharges',
            'portCharges',
            'miscCharges'
        ])->notDeleted()->find($id);

        if (!$ap) {
            return response()->json(['message' => 'Accounts payable record not found'], 404);
        }

        return response()->json($ap);
    }

    public function update(Request $request, $id)
    {
        $ap = AccountsPayable::notDeleted()->find($id);

        if (!$ap) {
            return response()->json(['message' => 'Accounts payable record not found'], 404);
        }

        $validated = $request->validate([
            'is_paid' => 'sometimes|boolean',
        ]);

        $ap->update($validated);

        return response()->json($ap);
    }

    public function destroy($id)
    {
        $ap = AccountsPayable::notDeleted()->find($id);

        if (!$ap) {
            return response()->json(['message' => 'Accounts payable record not found'], 404);
        }

        $ap->update(['is_deleted' => true]);

        return response()->json(['message' => 'Accounts payable record deleted successfully'], 200);
    }

    public function updateChargeStatus(Request $request, $apId, $chargeType, $chargeId)
    {
        $validated = $request->validate([
            'is_paid' => 'required|boolean',
            'voucher' => 'nullable|string|max:100',
            'check_date' => 'nullable|date',
        ]);

        $ap = AccountsPayable::notDeleted()->find($apId);
        if (!$ap) {
            return response()->json(['message' => 'Accounts payable record not found'], 404);
        }

        // Determine which model to use based on charge type
        switch ($chargeType) {
            case 'freight':
                $charge = ApFreightCharge::where('ap_id', $apId)->first();
                break;
            case 'trucking':
                $charge = ApTruckingCharge::where('ap_id', $apId)->where('id', $chargeId)->first();
                break;
            case 'port':
                $charge = ApPortCharge::where('ap_id', $apId)->where('id', $chargeId)->first();
                break;
            case 'misc':
                $charge = ApMiscCharge::where('ap_id', $apId)->where('id', $chargeId)->first();
                break;
            default:
                return response()->json(['message' => 'Invalid charge type'], 400);
        }

        if (!$charge) {
            return response()->json(['message' => 'Charge not found'], 404);
        }

        $charge->update($validated);

        // Check if all charges are paid and update main AP record
        $ap->update([
            'is_paid' => $ap->all_charges_paid
        ]);

        return response()->json([
            'message' => 'Charge status updated successfully',
            'charge' => $charge
        ]);
    }
    
    // Add this method to AccountsPayableController.php
public function getByBooking($bookingId)
{
    $ap = AccountsPayable::with([
        'freightCharge',
        'truckingCharges',
        'portCharges',
        'miscCharges'
    ])->where('booking_id', $bookingId)->notDeleted()->first();

    if (!$ap) {
        return response()->json(['message' => 'No accounts payable record found for this booking'], 404);
    }

    return response()->json($ap);
}
}