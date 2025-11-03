<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\BookingItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BookingController extends Controller
{
    public function index(Request $request)
    {
        $perPage = $request->get('per_page', 10);
        $search = $request->get('search', '');

        $query = Booking::with(['containerSize', 'origin', 'destination', 'shippingLine', 'items'])
            ->notDeleted();

        if (!empty($search)) {
            $query->where(function($q) use ($search) {
                $q->where('first_name', 'like', '%' . $search . '%')
                  ->orWhere('last_name', 'like', '%' . $search . '%')
                  ->orWhere('email', 'like', '%' . $search . '%');
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
                // Personal Information
                'first_name' => 'required|string|max:255',
                'last_name' => 'required|string|max:255',
                'email' => 'required|email',
                'contact_number' => 'nullable|string',
                
                // Shipper Information
                'shipper_first_name' => 'required|string|max:255',
                'shipper_last_name' => 'required|string|max:255',
                'shipper_contact' => 'nullable|string',
                
                // Consignee Information
                'consignee_first_name' => 'required|string|max:255',
                'consignee_last_name' => 'required|string|max:255',
                'consignee_contact' => 'nullable|string',
                
                // Shipping Details
                'mode_of_service' => 'required|string',
                'container_size_id' => 'required|exists:container_types,id',
                'container_quantity' => 'required|integer|min:1',
                'origin_id' => 'required|exists:ports,id',
                'destination_id' => 'required|exists:ports,id',
                'shipping_line_id' => 'nullable|exists:shipping_lines,id',
                
                // Dates
                'departure_date' => 'required|date',
                'delivery_date' => 'nullable|date|after_or_equal:departure_date',
                
                // Terms
                'terms' => 'required|integer|min:1',
                
                // Locations
                'pickup_location' => 'nullable|array',
                'delivery_location' => 'nullable|array',
                
                // Items
                'items' => 'required|array',
                'items.*.name' => 'required|string',
                'items.*.weight' => 'required|numeric|min:0',
                'items.*.quantity' => 'required|integer|min:1',
                'items.*.category' => 'required|string',
            ]);

            // Create booking
            $booking = Booking::create(array_merge($validated, [
                'booking_status' => 'pending',
                'status' => 'pending',
                'is_deleted' => false
            ]));

            // Create booking items
            foreach ($validated['items'] as $itemData) {
                BookingItem::create([
                    'booking_id' => $booking->id,
                    'name' => $itemData['name'],
                    'weight' => $itemData['weight'],
                    'quantity' => $itemData['quantity'],
                    'category' => $itemData['category'],
                ]);
            }

            DB::commit();

            return response()->json([
                'message' => 'Booking created successfully',
                'booking' => $booking->load('items')
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to create booking',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function show($id)
    {
        $booking = Booking::with(['containerSize', 'origin', 'destination', 'shippingLine', 'items'])
            ->notDeleted()
            ->find($id);

        if (!$booking) {
            return response()->json(['message' => 'Booking not found'], 404);
        }

        return response()->json($booking);
    }

    public function update(Request $request, $id)
    {
        $booking = Booking::notDeleted()->find($id);

        if (!$booking) {
            return response()->json(['message' => 'Booking not found'], 404);
        }

        $validated = $request->validate([
            'status' => 'sometimes|in:pending,approved,rejected',
            'booking_status' => 'sometimes|in:pending,in_transit,delivered',
            'first_name' => 'sometimes|string|max:255',
            'last_name' => 'sometimes|string|max:255',
            'email' => 'sometimes|email',
            'terms' => 'sometimes|integer|min:1',
            // Add other fields as needed
        ]);

        $booking->update($validated);

        return response()->json($booking);
    }

    public function destroy($id)
    {
        $booking = Booking::notDeleted()->find($id);

        if (!$booking) {
            return response()->json(['message' => 'Booking not found'], 404);
        }

        $booking->update(['is_deleted' => true]);

        return response()->json(['message' => 'Booking deleted successfully'], 200);
    }

    public function bulkDestroy(Request $request)
    {
        $validated = $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'exists:bookings,id',
        ]);

        $ids = $validated['ids'];
        Booking::whereIn('id', $ids)
            ->notDeleted()
            ->update(['is_deleted' => true]);

        return response()->json(['message' => count($ids) . ' bookings deleted successfully'], 200);
    }

    public function restore($id)
    {
        $booking = Booking::find($id);

        if (!$booking || !$booking->is_deleted) {
            return response()->json(['message' => 'Booking not found or not deleted'], 404);
        }

        $booking->update(['is_deleted' => false]);

        return response()->json(['message' => 'Booking restored successfully'], 200);
    }
}