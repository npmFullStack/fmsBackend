<?php

namespace App\Http\Controllers;

use App\Models\Item;
use App\Models\Category;
use Illuminate\Http\Request;

class ItemController extends Controller
{
    public function index(Request $request)
    {
        $perPage = $request->get('per_page', 10);
        $search = $request->get('search', '');

        $query = Item::with('category')->where('is_deleted', false);

        if (!empty($search)) {
            $query->where('name', 'like', '%'. $search . '%');
        }

        $sort = $request->get('sort', 'id');
        $direction = $request->get('direction', 'asc');

        $items = $query->orderBy($sort, $direction)->paginate($perPage);

        return response()->json($items);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'category_id' => 'required|exists:categories,id',
            'weight' => 'required|numeric|min:0.01',
        ]);

        // Get category to calculate costs
        $category = Category::find($validated['category_id']);
        
        $baseFreightCost = $validated['weight'] * $category->base_rate;
        $totalCost = $baseFreightCost; // Add other fees here if needed

        $item = Item::create([
            'name' => $validated['name'],
            'category_id' => $validated['category_id'],
            'weight' => $validated['weight'],
            'base_freight_cost' => $baseFreightCost,
            'total_cost' => $totalCost,
            'is_deleted' => false
        ]);

        return response()->json($item, 201);
    }

    public function show($id)
    {
        $item = Item::with('category')->where('id', $id)->where('is_deleted', false)->first();
        
        if (!$item) {
            return response()->json(['message' => 'Item not found'], 404);
        }

        return response()->json($item);
    }

    public function update(Request $request, $id)
    {
        $item = Item::where('id', $id)->where('is_deleted', false)->first();
        
        if (!$item) {
            return response()->json(['message' => 'Item not found'], 404);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'category_id' => 'required|exists:categories,id',
            'weight' => 'required|numeric|min:0.01',
        ]);

        // Recalculate costs if category or weight changes
        $category = Category::find($validated['category_id']);
        $baseFreightCost = $validated['weight'] * $category->base_rate;
        $totalCost = $baseFreightCost;

        $item->update([
            'name' => $validated['name'],
            'category_id' => $validated['category_id'],
            'weight' => $validated['weight'],
            'base_freight_cost' => $baseFreightCost,
            'total_cost' => $totalCost
        ]);

        return response()->json($item);
    }

    public function destroy($id)
    {
        $item = Item::where('id', $id)->where('is_deleted', false)->first();
        
        if (!$item) {
            return response()->json(['message' => 'Item not found'], 404);
        }

        $item->update(['is_deleted' => true]);

        return response()->json(['message' => 'Item deleted successfully'], 200);
    }

    public function bulkDestroy(Request $request)
    {
        $validated = $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'exists:items,id'
        ]);

        $ids = $validated['ids'];
        
        Item::whereIn('id', $ids)
            ->where('is_deleted', false)
            ->update(['is_deleted' => true]);

        return response()->json([
            'message' => count($ids) . ' items deleted successfully'
        ], 200);
    }

    public function restore($id)
    {
        $item = Item::find($id);

        if (!$item || $item->is_deleted == false) {
            return response()->json(['message' => 'Item not found or not deleted'], 404);
        }

        $item->update(['is_deleted' => false]);

        return response()->json(['message' => 'Item restored successfully'], 200);
    }
}