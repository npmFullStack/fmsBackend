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
                $q->where('voucher_number', 'like', '%' . $search . '%')
                  ->orWhereHas('booking', function($q) use ($search) {
                      $q->where('booking_number', 'like', '%' . $search . '%')
                        ->orWhere('first_name', 'like', '%' . $search . '%')
                        ->orWhere('last_name', 'like', '%' . $search . '%');
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
                
                // Trucking charges
                'trucking_charges' => 'nullable|array',
                'trucking_charges.*.type' => 'required|in:ORIGIN,DESTINATION',
                'trucking_charges.*.amount' => 'required|numeric|min:0',
                'trucking_charges.*.check_date' => 'nullable|date',
                
                // Port charges
                'port_charges' => 'nullable|array',
                'port_charges.*.charge_type' => 'required|in:CRAINAGE,ARRASTRE_ORIGIN,ARRASTRE_DEST,WHARFAGE_ORIGIN,WHARFAGE_DEST,LABOR_ORIGIN,LABOR_DEST',
                'port_charges.*.payee' => 'nullable|string|max:255',
                'port_charges.*.amount' => 'required|numeric|min:0',
                'port_charges.*.check_date' => 'nullable|date',
                
                // Misc charges
                'misc_charges' => 'nullable|array',
                'misc_charges.*.charge_type' => 'required|in:REBATES,STORAGE,FACILITATION,DENR',
                'misc_charges.*.payee' => 'nullable|string|max:255',
                'misc_charges.*.amount' => 'required|numeric|min:0',
                'misc_charges.*.check_date' => 'nullable|date',
            ]);

            // Find existing AP record or create new one
            $ap = AccountsPayable::where('booking_id', $validated['booking_id'])->first();

            $isNewRecord = false;
            
            if (!$ap) {
                // Create new AP record
                $ap = AccountsPayable::create([
                    'booking_id' => $validated['booking_id'],
                    'voucher_number' => AccountsPayable::generateVoucherNumber(),
                    'is_paid' => false,
                    'is_deleted' => false,
                ]);
                $isNewRecord = true;
            }

            // ADD (not replace) charges to the AP record
            $this->addChargesToAP($ap, $validated);

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

        // Add trucking charges (allow multiple of same type)
        if (isset($validated['trucking_charges'])) {
            foreach ($validated['trucking_charges'] as $truckingCharge) {
                if ($truckingCharge['amount'] > 0) {
                    // Find existing trucking charge of same type
                    $existingTrucking = $ap->truckingCharges()
                        ->where('type', $truckingCharge['type'])
                        ->first();

                    if (!$existingTrucking) {
                        // Create new trucking charge
                        ApTruckingCharge::create([
                            'ap_id' => $ap->id,
                            'type' => $truckingCharge['type'],
                            'amount' => $truckingCharge['amount'],
                            'check_date' => $truckingCharge['check_date'] ?? null,
                            'voucher' => null,
                            'is_paid' => false,
                            'is_deleted' => false,
                        ]);
                    } else {
                        // Update existing trucking charge (add to existing amount)
                        $existingTrucking->update([
                            'amount' => $existingTrucking->amount + $truckingCharge['amount'],
                            'check_date' => $truckingCharge['check_date'] ?? $existingTrucking->check_date,
                        ]);
                    }
                }
            }
        }

        // Add port charges (allow multiple of same type)
        if (isset($validated['port_charges'])) {
            foreach ($validated['port_charges'] as $portCharge) {
                if ($portCharge['amount'] > 0) {
                    // Find existing port charge of same type
                    $existingPort = $ap->portCharges()
                        ->where('charge_type', $portCharge['charge_type'])
                        ->first();

                    if (!$existingPort) {
                        // Create new port charge
                        ApPortCharge::create([
                            'ap_id' => $ap->id,
                            'charge_type' => $portCharge['charge_type'],
                            'payee' => $portCharge['payee'] ?? null,
                            'amount' => $portCharge['amount'],
                            'check_date' => $portCharge['check_date'] ?? null,
                            'voucher' => null,
                            'is_paid' => false,
                            'is_deleted' => false,
                        ]);
                    } else {
                        // Update existing port charge (add to existing amount)
                        $existingPort->update([
                            'amount' => $existingPort->amount + $portCharge['amount'],
                            'payee' => $portCharge['payee'] ?? $existingPort->payee,
                            'check_date' => $portCharge['check_date'] ?? $existingPort->check_date,
                        ]);
                    }
                }
            }
        }

        // Add misc charges (allow multiple of same type)
        if (isset($validated['misc_charges'])) {
            foreach ($validated['misc_charges'] as $miscCharge) {
                if ($miscCharge['amount'] > 0) {
                    // Find existing misc charge of same type
                    $existingMisc = $ap->miscCharges()
                        ->where('charge_type', $miscCharge['charge_type'])
                        ->first();

                    if (!$existingMisc) {
                        // Create new misc charge
                        ApMiscCharge::create([
                            'ap_id' => $ap->id,
                            'charge_type' => $miscCharge['charge_type'],
                            'payee' => $miscCharge['payee'] ?? null,
                            'amount' => $miscCharge['amount'],
                            'check_date' => $miscCharge['check_date'] ?? null,
                            'voucher' => null,
                            'is_paid' => false,
                            'is_deleted' => false,
                        ]);
                    } else {
                        // Update existing misc charge (add to existing amount)
                        $existingMisc->update([
                            'amount' => $existingMisc->amount + $miscCharge['amount'],
                            'payee' => $miscCharge['payee'] ?? $existingMisc->payee,
                            'check_date' => $miscCharge['check_date'] ?? $existingMisc->check_date,
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
}