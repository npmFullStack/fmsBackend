<?php

namespace App\Http\Controllers;

use App\Models\CargoMonitoring;
use Illuminate\Http\Request;
use Carbon\Carbon; // Add this import

class CargoMonitoringController extends Controller
{
    /**
     * Display a listing of the cargo monitoring records.
     */
    public function index(Request $request)
    {
        try {
            $perPage = $request->get('per_page', 10);
            $search = $request->get('search', '');
            
            $query = CargoMonitoring::with([
                'booking', 
                'booking.containerSize', 
                'booking.origin', 
                'booking.destination', 
                'booking.items',
                'booking.shippingLine',
                'booking.truckComp'
            ]);

            // Apply search filter
            if (!empty($search)) {
                $query->whereHas('booking', function($q) use ($search) {
                    $q->where('first_name', 'like', '%' . $search . '%')
                      ->orWhere('last_name', 'like', '%' . $search . '%')
                      ->orWhere('email', 'like', '%' . $search . '%')
                      ->orWhere('booking_number', 'like', '%' . $search . '%')
                      ->orWhere('hwb_number', 'like', '%' . $search . '%')
                      ->orWhere('van_number', 'like', '%' . $search . '%');
                });
            }

            // Get only approved bookings
            $query->whereHas('booking', function($q) {
                $q->where('status', 'approved');
            });

            // Paginate results
            $cargoMonitoring = $query->paginate($perPage);

            return response()->json([
                'data' => $cargoMonitoring->items(),
                'current_page' => $cargoMonitoring->currentPage(),
                'last_page' => $cargoMonitoring->lastPage(),
                'per_page' => $cargoMonitoring->perPage(),
                'total' => $cargoMonitoring->total(),
                'from' => $cargoMonitoring->firstItem(),
                'to' => $cargoMonitoring->lastItem(),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to fetch cargo monitoring data',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get cargo monitoring by booking ID.
     */
    public function getByBooking($bookingId)
    {
        try {
            $cargoMonitoring = CargoMonitoring::with([
                'booking', 
                'booking.containerSize', 
                'booking.origin', 
                'booking.destination', 
                'booking.items'
            ])->where('booking_id', $bookingId)->first();

            if (!$cargoMonitoring) {
                return response()->json([
                    'message' => 'Cargo monitoring record not found for this booking'
                ], 404);
            }

            return response()->json($cargoMonitoring);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to fetch cargo monitoring data',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified cargo monitoring record.
     */
    public function show($id)
    {
        try {
            $cargoMonitoring = CargoMonitoring::with([
                'booking', 
                'booking.containerSize', 
                'booking.origin', 
                'booking.destination', 
                'booking.items'
            ])->find($id);

            if (!$cargoMonitoring) {
                return response()->json([
                    'message' => 'Cargo monitoring record not found'
                ], 404);
            }

            return response()->json($cargoMonitoring);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to fetch cargo monitoring data',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update the cargo status.
     */
    public function updateStatus(Request $request, $id)
    {
        $request->validate([
            'status' => 'required|string',
            'timestamp' => 'nullable|date'
        ]);

        $cargoMonitoring = CargoMonitoring::findOrFail($id);
        
        // FIX: Use Carbon::parse() instead of CargoMonitoring::parse()
        $timestamp = $request->timestamp ? Carbon::parse($request->timestamp) : now();
        
        // Update the specific status field based on the status
        $statusFieldMap = [
            'Picked Up' => 'picked_up_at',
            'Origin Port' => 'origin_port_at',
            'In Transit' => 'in_transit_at',
            'Destination Port' => 'destination_port_at',
            'Out for Delivery' => 'out_for_delivery_at',
            'Delivered' => 'delivered_at'
        ];

        if (isset($statusFieldMap[$request->status])) {
            $field = $statusFieldMap[$request->status];
            $cargoMonitoring->update([
                $field => $timestamp,
                'current_status' => $request->status
            ]);
        }

        return response()->json($cargoMonitoring);
    }
}