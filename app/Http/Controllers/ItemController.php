<?php
namespace App\Http\Controllers;

use App\Models\Item;
use App\Models\Category;
use App\Services\PricingService;
use Illuminate\Http\Request;

class ItemController extends Controller
{
    public function index(Request $request)
    {
        $perPage = $request->get('per_page', 10);
        $search = $request->get('search', '');

        $query = Item::with('category')->where('is_deleted', false);

        if (!empty($search)) {
            $query->where('name', 'like', '%' . $search . '%');
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
            'description' => 'nullable|string',
            'category_id' => 'required|exists:categories,id',
            'weight' => 'required|numeric|min:0.01',
            'base_price' => 'required|numeric|min:0'
        ]);

        $category = Category::find($validated['category_id']);
        
        // Calculate the price using PricingService
        $pricing = PricingService::calculateItemPrice(
            $validated['base_price'],
            $validated['weight'],
            $category
        );

        $item = Item::create([
            'name' => $validated['name'],
            'description' => $validated['description'],
            'category_id' => $validated['category_id'],
            'weight' => $validated['weight'],
            'base_price' => $validated['base_price'],
            'calculated_price' => $pricing['total_price'],
            'is_deleted' => false
        ]);

        // Include pricing breakdown in response
        $item->pricing_breakdown = $pricing;

        return response()->json($item, 201);
    }

    public function show($id)
    {
        $item = Item::with('category')->where('id', $id)->where('is_deleted', false)->first();

        if (!$item) {
            return response()->json(['message' => 'Item not found'], 404);
        }

        // Calculate current pricing breakdown
        $pricing = PricingService::calculateItemPrice(
            $item->base_price,
            $item->weight,
            $item->category
        );

        $item->pricing_breakdown = $pricing;

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
            'description' => 'nullable|string',
            'category_id' => 'required|exists:categories,id',
            'weight' => 'required|numeric|min:0.01',
            'base_price' => 'required|numeric|min:0'
        ]);

        $category = Category::find($validated['category_id']);
        
        // Recalculate the price
        $pricing = PricingService::calculateItemPrice(
            $validated['base_price'],
            $validated['weight'],
            $category
        );

        $item->update([
            'name' => $validated['name'],
            'description' => $validated['description'],
            'category_id' => $validated['category_id'],
            'weight' => $validated['weight'],
            'base_price' => $validated['base_price'],
            'calculated_price' => $pricing['total_price']
        ]);

        // Include pricing breakdown in response
        $item->pricing_breakdown = $pricing;

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