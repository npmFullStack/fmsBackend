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

        $query = ShippingLine::where('is_deleted', 0);

        if (!empty($search)) {
            $query->where('name', 'like', '%'. $search. '%');
        }

        $sort = $request->get('sort', 'id');
        $direction = $request->get('direction', 'asc');

        $data = $query->orderBy($sort, $direction)->paginate($perPage);

        return response()->json($data);
    }

public function dropdown()
{
    $shippingLines = ShippingLine::where('is_deleted', 0)
        ->orderBy('name', 'asc')
        ->get();
        
    return response()->json($shippingLines);
}
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $shippingLine = ShippingLine::create([
            'name' => $validated['name'],
            'is_deleted' => 0
        ]);

        return response()->json($shippingLine, 201);
    }

    public function show($id)
    {
        $shippingLine = ShippingLine::where('id', $id)->where('is_deleted', 0)->first();

        if (!$shippingLine) {
            return response()->json(['message' => 'Shipping line not found'], 404);
        }

        return response()->json($shippingLine);
    }

    public function update(Request $request, $id)
    {
        $shippingLine = ShippingLine::where('id', $id)->where('is_deleted', 0)->first();

        if (!$shippingLine) {
            return response()->json(['message' => 'Shipping line not found'], 404);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $shippingLine->update($validated);

        return response()->json($shippingLine);
    }

    public function destroy($id)
    {
        $shippingLine = ShippingLine::where('id', $id)->where('is_deleted', 0)->first();

        if (!$shippingLine) {
            return response()->json(['message' => 'Shipping line not found'], 404);
        }

        $shippingLine->update(['is_deleted' => 1]);

        return response()->json(['message' => 'Shipping line deleted successfully'], 200);
    }

    public function bulkDestroy(Request $request)
    {
        $validated = $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'exists:shipping_lines,id',
        ]);

        $ids = $validated['ids'];
        ShippingLine::whereIn('id', $ids)
            ->where('is_deleted', 0)
            ->update(['is_deleted' => 1]);

        return response()->json(['message' => count($ids) . ' shipping lines deleted successfully'], 200);
    }

    public function restore($id)
    {
        $shippingLine = ShippingLine::find($id);

        if (!$shippingLine || $shippingLine->is_deleted == 0) {
            return response()->json(['message' => 'Shipping line not found or not deleted'], 404);
        }

        $shippingLine->update(['is_deleted' => 0]);

        return response()->json(['message' => 'Shipping line restored successfully'], 200);
    }
}