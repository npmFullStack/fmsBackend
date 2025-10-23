<?php

namespace App\Http\Controllers;

use App\Models\ShippingLine;
use Illuminate\Http\Request;

class ShippingLineController extends Controller
{
    public function index(Request $request)
    {
        $perPage = $request->get('per_page', 10);
        $search = $request->get('search', '');

        $query = ShippingLine::withCount('shipRoutes')
                    ->where('is_deleted', false);

        if (!empty($search)) {
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', '%'. $search . '%')
                  ->orWhere('base_rate_per_km', 'like', '%'. $search . '%')
                  ->orWhere('weight_rate_per_km', 'like', '%'. $search . '%');
            });
        }

        $sort = $request->get('sort', 'id');
        $direction = $request->get('direction', 'asc');

        $shippingLines = $query->orderBy($sort, $direction)->paginate($perPage);

        return response()->json($shippingLines);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:shipping_lines,name',
            'base_rate_per_km' => 'required|numeric|min:0',
            'weight_rate_per_km' => 'required|numeric|min:0',
            'min_charge' => 'required|numeric|min:0',
        ]);

        $shippingLine = ShippingLine::create(array_merge($validated, [
            'is_deleted' => false
        ]));

        return response()->json($shippingLine, 201);
    }

    public function show($id)
    {
        $shippingLine = ShippingLine::with(['shipRoutes.origin', 'shipRoutes.destination'])
                        ->where('id', $id)
                        ->where('is_deleted', false)
                        ->first();
        
        if (!$shippingLine) {
            return response()->json(['message' => 'Shipping line not found'], 404);
        }

        return response()->json($shippingLine);
    }

    public function update(Request $request, $id)
    {
        $shippingLine = ShippingLine::where('id', $id)->where('is_deleted', false)->first();
        
        if (!$shippingLine) {
            return response()->json(['message' => 'Shipping line not found'], 404);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:shipping_lines,name,' . $id,
            'base_rate_per_km' => 'required|numeric|min:0',
            'weight_rate_per_km' => 'required|numeric|min:0',
            'min_charge' => 'required|numeric|min:0',
        ]);

        $shippingLine->update($validated);

        return response()->json($shippingLine);
    }

    public function destroy($id)
    {
        $shippingLine = ShippingLine::where('id', $id)->where('is_deleted', false)->first();
        
        if (!$shippingLine) {
            return response()->json(['message' => 'Shipping line not found'], 404);
        }

        $shippingLine->update(['is_deleted' => true]);

        return response()->json(['message' => 'Shipping line deleted successfully'], 200);
    }

    public function bulkDestroy(Request $request)
    {
        $validated = $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'exists:shipping_lines,id'
        ]);

        $ids = $validated['ids'];
        
        ShippingLine::whereIn('id', $ids)
                   ->where('is_deleted', false)
                   ->update(['is_deleted' => true]);

        return response()->json([
            'message' => count($ids) . ' shipping lines deleted successfully'
        ], 200);
    }

    public function restore($id)
    {
        $shippingLine = ShippingLine::find($id);

        if (!$shippingLine || $shippingLine->is_deleted == false) {
            return response()->json(['message' => 'Shipping line not found or not deleted'], 404);
        }

        $shippingLine->update(['is_deleted' => false]);

        return response()->json(['message' => 'Shipping line restored successfully'], 200);
    }

    // Get all routes for a specific shipping line
    public function getRoutes($id)
    {
        $shippingLine = ShippingLine::with(['shipRoutes.origin', 'shipRoutes.destination'])
                        ->where('id', $id)
                        ->where('is_deleted', false)
                        ->first();
        
        if (!$shippingLine) {
            return response()->json(['message' => 'Shipping line not found'], 404);
        }

        return response()->json($shippingLine->shipRoutes);
    }
}