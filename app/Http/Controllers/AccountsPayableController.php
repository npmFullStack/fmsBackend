<?php
// [file name]: AccountsPayableController.php

namespace App\Http\Controllers;

use App\Models\AccountsPayable;
use App\Models\AccountsReceivable;
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
            'booking' => function($query) {
                $query->notDeleted(); // Only load non-deleted bookings
            },
            'booking.containerSize',
            'booking.origin',
            'booking.destination',
            'freightCharge' => function($query) {
                $query->notDeleted(); // Only load non-deleted charges
            },
            'truckingCharges' => function($query) {
                $query->notDeleted(); // Only load non-deleted charges
            },
            'portCharges' => function($query) {
                $query->notDeleted(); // Only load non-deleted charges
            },
            'miscCharges' => function($query) {
                $query->notDeleted(); // Only load non-deleted charges
            }
        ])->notDeleted()
          ->whereHas('booking', function($query) {
              $query->notDeleted(); // This is the key - only AP records with non-deleted bookings
          });

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

        // ✅ CRITICAL FIX: Refresh the AP instance to get latest relationships
        $ap->refresh();

        // Calculate and update total expenses
        $ap->calculateTotalAmount();

        // ✅ CRITICAL FIX: Get fresh instance with calculated total_expenses
        $ap = $ap->fresh();

        // ✅ CREATE OR UPDATE ACCOUNTS RECEIVABLE RECORD
        $ar = $this->updateAccountsReceivable($ap);

        DB::commit();

        // ✅ Load all relationships for the response
        $ap->load([
            'freightCharge',
            'truckingCharges', 
            'portCharges', 
            'miscCharges', 
            'booking' => function($query) {
                $query->with(['containerSize', 'origin', 'destination']);
            }
        ]);

        return response()->json([
            'message' => $isNewRecord ? 'Accounts payable record created successfully' : 'Additional charges added successfully',
            'accounts_payable' => $ap,
            // ✅ IMPORTANT: Include AR record in response
            'accounts_receivable' => $ar ? $ar->load([
                'booking' => function($query) {
                    $query->with(['containerSize', 'origin', 'destination']);
                }
            ]) : null
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
                // ✅ REPLACE existing amount instead of adding
                $existingFreight->update([
                    'amount' => $validated['freight_charge']['amount'], // ← Replace, don't add
                    'check_date' => $validated['freight_charge']['check_date'] ?? $existingFreight->check_date,
                ]);
            }
        }

        // Add trucking charges - REPLACE existing ones
        if (isset($validated['trucking_charges'])) {
            foreach ($validated['trucking_charges'] as $truckingCharge) {
                if ($truckingCharge['amount'] > 0) {
                    // Find existing trucking charge of the same type
                    $existingTrucking = ApTruckingCharge::where('ap_id', $ap->id)
                        ->where('type', $truckingCharge['type'])
                        ->where('is_deleted', false)
                        ->first();

                    if ($existingTrucking) {
                        // ✅ REPLACE existing charge amount
                        $existingTrucking->update([
                            'amount' => $truckingCharge['amount'], // ← Replace, don't add
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

        // ✅ ADD PORT CHARGES HANDLING
        if (isset($validated['port_charges'])) {
            foreach ($validated['port_charges'] as $portCharge) {
                if ($portCharge['amount'] > 0) {
                    // Find existing port charge of the same type
                    $existingPort = ApPortCharge::where('ap_id', $ap->id)
                        ->where('charge_type', $portCharge['charge_type'])
                        ->where('is_deleted', false)
                        ->first();

                    if ($existingPort) {
                        // ✅ REPLACE existing charge amount
                        $existingPort->update([
                            'amount' => $portCharge['amount'],
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

        // ✅ ADD MISC CHARGES HANDLING
        if (isset($validated['misc_charges'])) {
            foreach ($validated['misc_charges'] as $miscCharge) {
                if ($miscCharge['amount'] > 0) {
                    // Find existing misc charge of the same type
                    $existingMisc = ApMiscCharge::where('ap_id', $ap->id)
                        ->where('charge_type', $miscCharge['charge_type'])
                        ->where('is_deleted', false)
                        ->first();

                    if ($existingMisc) {
                        // ✅ REPLACE existing charge amount
                        $existingMisc->update([
                            'amount' => $miscCharge['amount'],
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

private function updateAccountsReceivable($ap)
{
    // Ensure we have the booking relationship loaded
    if (!$ap->relationLoaded('booking')) {
        $ap->load(['booking' => function($query) {
            $query->with(['containerSize', 'origin', 'destination']);
        }]);
    }
    
    $booking = $ap->booking;
    
    if (!$booking) {
        \Log::error('Booking not found for AP record: ' . $ap->id);
        return null;
    }

    // Calculate total expenses from AP (this is our cost basis)
    $totalExpenses = $ap->total_expenses;
    
    // Find existing AR or create new one
    $ar = AccountsReceivable::where('booking_id', $booking->id)->first();
    
    if ($ar) {
        // Update existing AR record with new expenses
        $ar->update([
            'total_expenses' => $totalExpenses,
        ]);
        
        \Log::info('Updated existing AR record', [
            'ar_id' => $ar->id,
            'booking_id' => $booking->id,
            'total_expenses' => $totalExpenses
        ]);
    } else {
        // Create new AR record
        $ar = AccountsReceivable::create([
            'booking_id' => $booking->id,
            'total_expenses' => $totalExpenses,
            'total_payment' => 0,
            'collectible_amount' => 0,
            'gross_income' => 0,
            'net_revenue' => 0,
            'profit' => 0,
            'is_paid' => false,
            'is_deleted' => false,
        ]);
        
        \Log::info('Created new AR record', [
            'ar_id' => $ar->id,
            'booking_id' => $booking->id,
            'total_expenses' => $totalExpenses
        ]);
    }
    
    // Calculate financials and save
    $ar->calculateFinancials();
    $ar->save();
    
    // Load relationships for the response
    $ar->load([
        'booking' => function($query) {
            $query->with(['containerSize', 'origin', 'destination']);
        }
    ]);
    
    return $ar;
}

    public function show($id)
    {
        $ap = AccountsPayable::with([
            'booking' => function($query) {
                $query->notDeleted();
            },
            'booking.containerSize',
            'booking.origin',
            'booking.destination',
            'booking.shippingLine',
            'booking.truckComp',
            'freightCharge' => function($query) {
                $query->notDeleted();
            },
            'truckingCharges' => function($query) {
                $query->notDeleted();
            },
            'portCharges' => function($query) {
                $query->notDeleted();
            },
            'miscCharges' => function($query) {
                $query->notDeleted();
            }
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
    

    public function getByBooking($bookingId)
    {
        $ap = AccountsPayable::with([
            'freightCharge' => function($query) {
                $query->notDeleted();
            },
            'truckingCharges' => function($query) {
                $query->notDeleted();
            },
            'portCharges' => function($query) {
                $query->notDeleted();
            },
            'miscCharges' => function($query) {
                $query->notDeleted();
            }
        ])->where('booking_id', $bookingId)->notDeleted()->first();

        if (!$ap) {
            return response()->json(['message' => 'No accounts payable record found for this booking'], 404);
        }

        return response()->json($ap);
    }

    // Pay Charges related methods

    public function getPayableCharges(Request $request)
    {
        $perPage = $request->get('per_page', 10);
        $search = $request->get('search', '');
        $status = $request->get('status', 'unpaid'); // unpaid, paid, all

        $query = AccountsPayable::with([
            'booking' => function($query) {
                $query->notDeleted();
            },
            'booking.containerSize',
            'booking.origin',
            'booking.destination',
            'freightCharge' => function($query) {
                $query->notDeleted();
            },
            'truckingCharges' => function($query) {
                $query->notDeleted();
            },
            'portCharges' => function($query) {
                $query->notDeleted();
            },
            'miscCharges' => function($query) {
                $query->notDeleted();
            }
        ])->notDeleted()
          ->whereHas('booking', function($query) {
              $query->notDeleted();
          });

        // Filter by unpaid charges
        if ($status === 'unpaid') {
            $query->where(function($q) {
                $q->whereHas('freightCharge', function($q) {
                    $q->where('is_paid', false);
                })
                ->orWhereHas('truckingCharges', function($q) {
                    $q->where('is_paid', false);
                })
                ->orWhereHas('portCharges', function($q) {
                    $q->where('is_paid', false);
                })
                ->orWhereHas('miscCharges', function($q) {
                    $q->where('is_paid', false);
                });
            });
        } elseif ($status === 'paid') {
            $query->where('is_paid', true);
        }

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

        $sort = $request->get('sort', 'id');
        $direction = $request->get('direction', 'desc');

        $data = $query->orderBy($sort, $direction)->paginate($perPage);

        // Add unpaid charges count and total unpaid amount to each record
        $data->getCollection()->transform(function ($ap) {
            $ap->unpaid_charges_count = $this->countUnpaidCharges($ap);
            $ap->total_unpaid_amount = $this->calculateUnpaidAmount($ap);
            return $ap;
        });

        return response()->json($data);
    }
    
    /**
     * Get payable charges for a specific booking
     */
    public function getPayableChargesByBooking($bookingId)
    {
        $ap = AccountsPayable::with([
            'booking' => function($query) {
                $query->notDeleted();
            },
            'booking.containerSize',
            'booking.origin',
            'booking.destination',
            'freightCharge' => function($query) {
                $query->notDeleted();
            },
            'truckingCharges' => function($query) {
                $query->notDeleted();
            },
            'portCharges' => function($query) {
                $query->notDeleted();
            },
            'miscCharges' => function($query) {
                $query->notDeleted();
            }
        ])->where('booking_id', $bookingId)->notDeleted()->first();

        if (!$ap) {
            return response()->json(['message' => 'No accounts payable record found for this booking'], 404);
        }

        // Add unpaid charges information
        $ap->unpaid_charges_count = $this->countUnpaidCharges($ap);
        $ap->total_unpaid_amount = $this->calculateUnpaidAmount($ap);
        $ap->all_charges = $this->getAllCharges($ap);

        return response()->json($ap);
    }

    /**
     * Mark multiple charges as paid
     */
    public function markMultipleChargesAsPaid(Request $request)
    {
        DB::beginTransaction();

        try {
            $validated = $request->validate([
                'charges' => 'required|array',
                'charges.*.ap_id' => 'required|exists:accounts_payables,id',
                'charges.*.charge_type' => 'required|in:freight,trucking,port,misc',
                'charges.*.charge_id' => 'required',
                'charges.*.voucher' => 'nullable|string|max:100',
                'charges.*.check_date' => 'nullable|date',
            ]);

            $results = [];

            foreach ($validated['charges'] as $chargeData) {
                $result = $this->markSingleChargeAsPaid(
                    $chargeData['ap_id'],
                    $chargeData['charge_type'],
                    $chargeData['charge_id'],
                    [
                        'voucher' => $chargeData['voucher'] ?? null,
                        'check_date' => $chargeData['check_date'] ?? null,
                    ]
                );

                $results[] = $result;
            }

            DB::commit();

            return response()->json([
                'message' => 'Charges marked as paid successfully',
                'results' => $results
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to mark charges as paid',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Mark a single charge as paid
     */
    public function markChargesAsPaid(Request $request)
    {
        $validated = $request->validate([
            'ap_id' => 'required|exists:accounts_payables,id',
            'charge_type' => 'required|in:freight,trucking,port,misc',
            'charge_id' => 'required',
            'voucher' => 'nullable|string|max:100',
            'check_date' => 'nullable|date',
        ]);

        try {
            $result = $this->markSingleChargeAsPaid(
                $validated['ap_id'],
                $validated['charge_type'],
                $validated['charge_id'],
                [
                    'voucher' => $validated['voucher'] ?? null,
                    'check_date' => $validated['check_date'] ?? null,
                ]
            );

            return response()->json([
                'message' => 'Charge marked as paid successfully',
                'charge' => $result['charge'],
                'ap_record' => $result['ap_record']
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to mark charge as paid',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Helper method to mark a single charge as paid
     */
    private function markSingleChargeAsPaid($apId, $chargeType, $chargeId, $paymentData)
    {
        $ap = AccountsPayable::notDeleted()->find($apId);
        if (!$ap) {
            throw new \Exception('Accounts payable record not found');
        }

        // Determine which model to use based on charge type
        switch ($chargeType) {
            case 'freight':
                $charge = ApFreightCharge::where('ap_id', $apId)->first();
                if (!$charge) {
                    throw new \Exception('Freight charge not found');
                }
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
                throw new \Exception('Invalid charge type');
        }

        if (!$charge) {
            throw new \Exception('Charge not found');
        }

        // Update charge with payment data
        $charge->update([
            'is_paid' => true,
            'voucher' => $paymentData['voucher'],
            'check_date' => $paymentData['check_date'],
        ]);

        // Check if all charges are paid and update main AP record
        $ap->update([
            'is_paid' => $ap->all_charges_paid
        ]);

        // Reload relationships
        $ap->load(['freightCharge', 'truckingCharges', 'portCharges', 'miscCharges']);

        return [
            'charge' => $charge,
            'ap_record' => $ap
        ];
    }

    /**
     * Count unpaid charges for an AP record
     */
    private function countUnpaidCharges($ap)
    {
        $count = 0;

        if ($ap->freight_charge && !$ap->freight_charge->is_paid) {
            $count++;
        }

        if ($ap->trucking_charges) {
            $count += $ap->trucking_charges->where('is_paid', false)->count();
        }

        if ($ap->port_charges) {
            $count += $ap->port_charges->where('is_paid', false)->count();
        }

        if ($ap->misc_charges) {
            $count += $ap->misc_charges->where('is_paid', false)->count();
        }

        return $count;
    }

    /**
     * Calculate total unpaid amount for an AP record
     */
    private function calculateUnpaidAmount($ap)
    {
        $total = 0;

        if ($ap->freight_charge && !$ap->freight_charge->is_paid) {
            $total += $ap->freight_charge->amount;
        }

        if ($ap->trucking_charges) {
            $total += $ap->trucking_charges->where('is_paid', false)->sum('amount');
        }

        if ($ap->port_charges) {
            $total += $ap->port_charges->where('is_paid', false)->sum('amount');
        }

        if ($ap->misc_charges) {
            $total += $ap->misc_charges->where('is_paid', false)->sum('amount');
        }

        return $total;
    }

    /**
     * Get all charges in a unified format
     */
    private function getAllCharges($ap)
    {
        $charges = [];

        // Freight charge
        if ($ap->freight_charge) {
            $charges[] = [
                'type' => 'freight',
                'id' => $ap->freight_charge->id,
                'charge_type' => 'FREIGHT',
                'voucher_number' => $ap->freight_charge->voucher_number,
                'payee' => 'Freight Charge',
                'amount' => $ap->freight_charge->amount,
                'check_date' => $ap->freight_charge->check_date,
                'voucher' => $ap->freight_charge->voucher,
                'is_paid' => $ap->freight_charge->is_paid,
                'created_at' => $ap->freight_charge->created_at,
                'updated_at' => $ap->freight_charge->updated_at,
            ];
        }

        // Trucking charges
        if ($ap->trucking_charges) {
            foreach ($ap->trucking_charges as $charge) {
                $charges[] = [
                    'type' => 'trucking',
                    'id' => $charge->id,
                    'charge_type' => 'TRUCKING_' . $charge->type,
                    'voucher_number' => $charge->voucher_number,
                    'payee' => 'Trucking - ' . $charge->type,
                    'amount' => $charge->amount,
                    'check_date' => $charge->check_date,
                    'voucher' => $charge->voucher,
                    'is_paid' => $charge->is_paid,
                    'created_at' => $charge->created_at,
                    'updated_at' => $charge->updated_at,
                ];
            }
        }

        // Port charges
        if ($ap->port_charges) {
            foreach ($ap->port_charges as $charge) {
                $charges[] = [
                    'type' => 'port',
                    'id' => $charge->id,
                    'charge_type' => $charge->charge_type,
                    'voucher_number' => $charge->voucher_number,
                    'payee' => $charge->payee ?: 'Port - ' . $charge->charge_type,
                    'amount' => $charge->amount,
                    'check_date' => $charge->check_date,
                    'voucher' => $charge->voucher,
                    'is_paid' => $charge->is_paid,
                    'created_at' => $charge->created_at,
                    'updated_at' => $charge->updated_at,
                ];
            }
        }

        // Misc charges
        if ($ap->misc_charges) {
            foreach ($ap->misc_charges as $charge) {
                $charges[] = [
                    'type' => 'misc',
                    'id' => $charge->id,
                    'charge_type' => $charge->charge_type,
                    'voucher_number' => $charge->voucher_number,
                    'payee' => $charge->payee ?: 'Misc - ' . $charge->charge_type,
                    'amount' => $charge->amount,
                    'check_date' => $charge->check_date,
                    'voucher' => $charge->voucher,
                    'is_paid' => $charge->is_paid,
                    'created_at' => $charge->created_at,
                    'updated_at' => $charge->updated_at,
                ];
            }
        }

        return $charges;
    }
}