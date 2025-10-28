<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use Illuminate\Http\Request;

class BookingController extends Controller
{
    public function index(Request $request)
    {
        $perPage = $request->get('per_page', 10);
        $search = $request->get('search', '');

        $query = Booking::where('is_deleted', false);

        if (!empty($search)) {
            $query->where(function($q) use ($search) {
                $q->where('first_name', 'like', '%'. $search . '%')
                  ->orWhere('last_name', 'like', '%'. $search . '%')
                  ->orWhere('email', 'like', '%'. $search . '%')
                  ->orWhere('origin', 'like', '%'. $search . '%')
                  ->orWhere('destination', 'like', '%'. $search . '%');
            });
        }

        $sort = $request->get('sort', 'id');
        $direction = $request->get('direction', 'asc');

        $bookings = $query->orderBy($sort, $direction)->paginate($perPage);

        return response()->json($bookings);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            // Personal Information
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'contact_number' => 'required|string|max:20',
            
            // Shipper Information
            'shipper_first_name' => 'required|string|max:255',
            'shipper_last_name' => 'required|string|max:255',
            'shipper_contact' => 'required|string|max:20',
            
            // Consignee Information
            'consignee_first_name' => 'required|string|max:255',
            'consignee_last_name' => 'required|string|max:255',
            'consignee_contact' => 'required|string|max:20',
            
            // Shipping Preferences
            'mode_of_service' => 'required|string|max:50',
            'container_size' => 'required|string|max:50',
            'origin' => 'required|string|max:255',
            'destination' => 'required|string|max:255',
            'shipping_line' => 'nullable|string|max:255',
            'departure_date' => 'required|date',
            'delivery_date' => 'nullable|date|after_or_equal:departure_date',
            
            // Location data
            'pickup_location' => 'nullable|array',
            'delivery_location' => 'nullable|array',
            
            // Items data
            'items' => 'required|array',
            'items.*.name' => 'required|string|max:255',
            'items.*.weight' => 'required|numeric|min:0',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.category' => 'required|string|max:255',
        ]);

        $booking = Booking::create(array_merge($validated, [
            'status' => 'pending',
            'is_deleted' => false
        ]));

        return response()->json($booking, 201);
    }

    public function show($id)
    {
        $booking = Booking::where('id', $id)->where('is_deleted', false)->first();

        if (!$booking) {
            return response()->json(['message' => 'Booking not found'], 404);
        }

        return response()->json($booking);
    }

    public function update(Request $request, $id)
    {
        $booking = Booking::where('id', $id)->where('is_deleted', false)->first();

        if (!$booking) {
            return response()->json(['message' => 'Booking not found'], 404);
        }

        $validated = $request->validate([
            'first_name' => 'sometimes|required|string|max:255',
            'last_name' => 'sometimes|required|string|max:255',
            'email' => 'sometimes|required|email|max:255',
            'contact_number' => 'sometimes|required|string|max:20',
            'shipper_first_name' => 'sometimes|required|string|max:255',
            'shipper_last_name' => 'sometimes|required|string|max:255',
            'shipper_contact' => 'sometimes|required|string|max:20',
            'consignee_first_name' => 'sometimes|required|string|max:255',
            'consignee_last_name' => 'sometimes|required|string|max:255',
            'consignee_contact' => 'sometimes|required|string|max:20',
            'mode_of_service' => 'sometimes|required|string|max:50',
            'container_size' => 'sometimes|required|string|max:50',
            'origin' => 'sometimes|required|string|max:255',
            'destination' => 'sometimes|required|string|max:255',
            'shipping_line' => 'nullable|string|max:255',
            'departure_date' => 'sometimes|required|date',
            'delivery_date' => 'nullable|date|after_or_equal:departure_date',
            'pickup_location' => 'nullable|array',
            'delivery_location' => 'nullable|array',
            'items' => 'sometimes|required|array',
            'items.*.name' => 'sometimes|required|string|max:255',
            'items.*.weight' => 'sometimes|required|numeric|min:0',
            'items.*.quantity' => 'sometimes|required|integer|min:1',
            'items.*.category' => 'sometimes|required|string|max:255',
            'status' => 'sometimes|required|in:pending,approved,rejected',
        ]);

        $booking->update($validated);

        return response()->json($booking);
    }

    public function destroy($id)
    {
        $booking = Booking::where('id', $id)->where('is_deleted', false)->first();

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
            'ids.*' => 'exists:bookings,id'
        ]);

        $ids = $validated['ids'];

        Booking::whereIn('id', $ids)
            ->where('is_deleted', false)
            ->update(['is_deleted' => true]);

        return response()->json(['message' => count($ids) . ' bookings deleted successfully'], 200);
    }

    public function restore($id)
    {
        $booking = Booking::find($id);

        if (!$booking || $booking->is_deleted == false) {
            return response()->json(['message' => 'Booking not found or not deleted'], 404);
        }

        $booking->update(['is_deleted' => false]);

        return response()->json(['message' => 'Booking restored successfully'], 200);
    }

    public function updateStatus(Request $request, $id)
    {
        $booking = Booking::where('id', $id)->where('is_deleted', false)->first();

        if (!$booking) {
            return response()->json(['message' => 'Booking not found'], 404);
        }

        $validated = $request->validate([
            'status' => 'required|in:pending,approved,rejected'
        ]);

        $booking->update(['status' => $validated['status']]);

        return response()->json($booking);
    }
}