<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\BookingItem;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class BookingController extends Controller
{
    public function index(Request $request)
    {
        $perPage = $request->get('per_page', 10);
        $search = $request->get('search', '');

        $query = Booking::with(['containerSize', 'origin', 'destination', 'shippingLine', 'truckComp', 'items', 'user'])
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
public function quote(Request $request)
{
    DB::beginTransaction();

    try {
        $validated = $request->validate([
            // Customer Information (for quote - no user_id required)
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
            'truck_comp_id' => 'nullable|exists:truck_comps,id',

            // Dates
            'departure_date' => 'nullable|date',
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

        // Create booking without user_id (it will be set when approved)
        $booking = Booking::create(array_merge($validated, [
            'user_id' => null, // Will be set when approved
            'booking_status' => 'pending',
            'status' => 'pending',
            'is_deleted' => false,
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
            'message' => 'Quote request submitted successfully',
            'quote_id' => $booking->id,
            'booking' => $booking->load(['items', 'truckComp', 'containerSize', 'origin', 'destination'])
        ], 201);

    } catch (Exception $e) {
        DB::rollBack();
        return response()->json([
            'message' => 'Failed to submit quote request',
            'error' => $e->getMessage()
        ], 500);
    }
}

public function store(Request $request)
{
    DB::beginTransaction();

    try {
        $validated = $request->validate([
            // User ID (customer)
            'user_id' => 'required|exists:users,id',
            
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
            'truck_comp_id' => 'nullable|exists:truck_comps,id',
            
            // Dates
            'departure_date' => 'nullable|date', 
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

        // Get the user information
        $user = User::findOrFail($validated['user_id']);

        // Generate tracking numbers
        $bookingNumber = Booking::generateBookingNumber();
        $hwbNumber = Booking::generateHwbNumber();
        $vanNumber = Booking::generateVanNumber();

        // Create booking with user's information and tracking numbers
        $booking = Booking::create(array_merge($validated, [
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'email' => $user->email,
            'contact_number' => $user->contact_number,
            'booking_number' => $bookingNumber,
            'hwb_number' => $hwbNumber,
            'van_number' => $vanNumber,
            'booking_status' => 'in_transit', // Set to in_transit since it's approved
            'status' => 'approved', // Set to approved by default
            'is_deleted' => false,
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

        // CREATE CARGO MONITORING RECORD
        \App\Models\CargoMonitoring::create([
            'booking_id' => $booking->id,
            'pending_at' => now(),
            'current_status' => 'Pending'
        ]);

        DB::commit();

        return response()->json([
            'message' => 'Booking created successfully',
            'booking' => $booking->load(['items', 'truckComp', 'user', 'containerSize', 'origin', 'destination', 'cargoMonitoring'])
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
        $booking = Booking::with(['containerSize', 'origin', 'destination', 'shippingLine', 'truckComp', 'items', 'user'])
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
            'truck_comp_id' => 'nullable|exists:truck_comps,id',
            'terms' => 'sometimes|integer|min:1',
        ]);

        $booking->update($validated);

        return response()->json($booking);
    }

public function approveBooking(Request $request, $id)
{
    DB::beginTransaction();

    try {
        $booking = Booking::notDeleted()->find($id);

        if (!$booking) {
            return response()->json(['message' => 'Booking not found'], 404);
        }

        if ($booking->status === 'approved') {
            return response()->json(['message' => 'Booking is already approved'], 400);
        }

        // Generate random password
        $password = Str::random(8);

        // Find or create user
        $user = User::where('email', $booking->email)->first();

        if (!$user) {
            // Create new user
            $user = User::create([
                'first_name' => $booking->first_name,
                'last_name' => $booking->last_name,
                'email' => $booking->email,
                'contact_number' => $booking->contact_number,
                'password' => Hash::make($password),
                'email_verified_at' => now(),
                'is_deleted' => false,
                'role' => 'customer', // Make sure to set the role
            ]);
        } else {
            // Update existing user's password
            $user->update([
                'password' => Hash::make($password),
                'is_deleted' => false,
            ]);
        }

        // Generate tracking numbers
        $booking->generateTrackingNumbers();

        // Update booking
        $booking->update([
            'user_id' => $user->id,
            'status' => 'approved',
            'booking_status' => 'in_transit',
        ]);

        // CREATE OR UPDATE CARGO MONITORING RECORD
        \App\Models\CargoMonitoring::create([
            'booking_id' => $booking->id,
            'pending_at' => now(),
            'current_status' => 'Pending'
        ]);

        // Send email with password
        $this->sendApprovalEmail($booking, $password);

        DB::commit();

        return response()->json([
            'message' => 'Booking approved successfully. Password sent to customer.',
            'booking' => $booking->load(['user', 'containerSize', 'origin', 'destination', 'shippingLine', 'truckComp', 'items', 'cargoMonitoring'])
        ]);
    } catch (\Exception $e) {
        DB::rollBack();
        return response()->json([
            'message' => 'Failed to approve booking',
            'error' => $e->getMessage()
        ], 500);
    }
}
   

private function sendApprovalEmail($booking, $password)
{
    try {
        // Fixed: Use Mail::to instead of Malinto
        Mail::to($booking->email)->send(new \App\Mail\BookingApproved($booking, $password));

        \Log::info('Approval email sent successfully', [
            'to' => $booking->email,
            'booking_id' => $booking->id
        ]);
    } catch (Exception $e) {
        \Log::error('Failed to send approval email: '. $e->getMessage(), [
            'to' => $booking->email,
            'booking_id' => $booking->id,
            'error' => $e->getMessage()
        ]);
        // You can choose to throw the exception or just log it
        throw $e; // Uncomment this if you want the approval to fail when email fails
    }
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