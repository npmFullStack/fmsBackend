<?php

namespace App\Http\Controllers;

use App\Models\ShipRoute;
use App\Models\Port;
use Illuminate\Http\Request;

class ShipRouteController extends Controller
{
    public function index(Request $request)
    {
        $perPage = $request->get('per_page', 10);
        $search = $request->get('search', '');

        $query = ShipRoute::with(['origin', 'destination'])
                    ->where('is_deleted', false);

        if (!empty($search)) {
            $query->where(function($q) use ($search) {
                $q->whereHas('origin', function($originQuery) use ($search) {
                    $originQuery->where('name', 'like', '%'. $search . '%')
                               ->orWhere('route_name', 'like', '%'. $search . '%');
                })
                ->orWhereHas('destination', function($destinationQuery) use ($search) {
                    $destinationQuery->where('name', 'like', '%'. $search . '%')
                                   ->orWhere('route_name', 'like', '%'. $search . '%');
                })
                ->orWhere('distance_km', 'like', '%'. $search . '%');
            });
        }

        $sort = $request->get('sort', 'id');
        $direction = $request->get('direction', 'asc');

        $shipRoutes = $query->orderBy($sort, $direction)->paginate($perPage);

        return response()->json($shipRoutes);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'origin_id' => 'required|exists:ports,id',
            'destination_id' => 'required|exists:ports,id|different:origin_id',
            'distance_km' => 'required|numeric|min:0.01',
        ]);

        // Check if route already exists (same origin and destination)
        $existingRoute = ShipRoute::where('origin_id', $validated['origin_id'])
                                ->where('destination_id', $validated['destination_id'])
                                ->where('is_deleted', false)
                                ->first();

        if ($existingRoute) {
            return response()->json([
                'message' => 'A ship route between these ports already exists'
            ], 422);
        }

        $shipRoute = ShipRoute::create(array_merge($validated, [
            'is_deleted' => false
        ]));

        // Load relationships for response
        $shipRoute->load(['origin', 'destination']);

        return response()->json($shipRoute, 201);
    }

    public function show($id)
    {
        $shipRoute = ShipRoute::with(['origin', 'destination'])
                        ->where('id', $id)
                        ->where('is_deleted', false)
                        ->first();
        
        if (!$shipRoute) {
            return response()->json(['message' => 'Ship route not found'], 404);
        }

        return response()->json($shipRoute);
    }

    public function update(Request $request, $id)
    {
        $shipRoute = ShipRoute::where('id', $id)->where('is_deleted', false)->first();
        
        if (!$shipRoute) {
            return response()->json(['message' => 'Ship route not found'], 404);
        }

        $validated = $request->validate([
            'origin_id' => 'required|exists:ports,id',
            'destination_id' => 'required|exists:ports,id|different:origin_id',
            'distance_km' => 'required|numeric|min:0.01',
        ]);

        // Check if another route with same origin/destination exists (excluding current)
        $existingRoute = ShipRoute::where('origin_id', $validated['origin_id'])
                                ->where('destination_id', $validated['destination_id'])
                                ->where('id', '!=', $id)
                                ->where('is_deleted', false)
                                ->first();

        if ($existingRoute) {
            return response()->json([
                'message' => 'Another ship route between these ports already exists'
            ], 422);
        }

        $shipRoute->update($validated);

        // Refresh relationships
        $shipRoute->load(['origin', 'destination']);

        return response()->json($shipRoute);
    }

    public function destroy($id)
    {
        $shipRoute = ShipRoute::where('id', $id)->where('is_deleted', false)->first();
        
        if (!$shipRoute) {
            return response()->json(['message' => 'Ship route not found'], 404);
        }

        $shipRoute->update(['is_deleted' => true]);

        return response()->json(['message' => 'Ship route deleted successfully'], 200);
    }

    public function bulkDestroy(Request $request)
    {
        $validated = $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'exists:ship_routes,id'
        ]);

        $ids = $validated['ids'];
        
        ShipRoute::whereIn('id', $ids)
                ->where('is_deleted', false)
                ->update(['is_deleted' => true]);

        return response()->json([
            'message' => count($ids) . ' ship routes deleted successfully'
        ], 200);
    }

    public function restore($id)
    {
        $shipRoute = ShipRoute::find($id);

        if (!$shipRoute || $shipRoute->is_deleted == false) {
            return response()->json(['message' => 'Ship route not found or not deleted'], 404);
        }

        $shipRoute->update(['is_deleted' => false]);

        return response()->json(['message' => 'Ship route restored successfully'], 200);
    }

    // Additional method to get routes by origin and destination
    public function getRouteBetweenPorts(Request $request)
    {
        $validated = $request->validate([
            'origin_id' => 'required|exists:ports,id',
            'destination_id' => 'required|exists:ports,id'
        ]);

        $shipRoute = ShipRoute::with(['origin', 'destination'])
                        ->where('origin_id', $validated['origin_id'])
                        ->where('destination_id', $validated['destination_id'])
                        ->where('is_deleted', false)
                        ->first();

        if (!$shipRoute) {
            return response()->json(['message' => 'No route found between these ports'], 404);
        }

        return response()->json($shipRoute);
    }
}