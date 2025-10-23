<?php
namespace App\Http\Controllers;

use App\Models\Category;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    public function index(Request $request)
    {
        $perPage = $request->get('per_page', 10);
        $search = $request->get('search', '');

        $query = Category::where('is_deleted', false);

        if (!empty($search)) {
            $query->where('name', 'like', '%' . $search . '%');
        }

        $sort = $request->get('sort', 'id');
        $direction = $request->get('direction', 'asc');

        $categories = $query->orderBy($sort, $direction)->paginate($perPage);

        return response()->json($categories);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:categories,name',
            'base_rate' => 'required|numeric|min:0',
            'weight_multiplier' => 'required|numeric|min:0',
            'surcharge_percentage' => 'required|numeric|min:0|max:100'
        ]);

        $category = Category::create($validated);

        return response()->json($category, 201);
    }

    public function show($id)
    {
        $category = Category::where('id', $id)->where('is_deleted', false)->first();

        if (!$category) {
            return response()->json(['message' => 'Category not found'], 404);
        }

        return response()->json($category);
    }

    public function update(Request $request, $id)
    {
        $category = Category::where('id', $id)->where('is_deleted', false)->first();

        if (!$category) {
            return response()->json(['message' => 'Category not found'], 404);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:categories,name,' . $id,
            'base_rate' => 'required|numeric|min:0',
            'weight_multiplier' => 'required|numeric|min:0',
            'surcharge_percentage' => 'required|numeric|min:0|max:100'
        ]);

        $category->update($validated);

        return response()->json($category);
    }

    public function destroy($id)
    {
        $category = Category::where('id', $id)->where('is_deleted', false)->first();

        if (!$category) {
            return response()->json(['message' => 'Category not found'], 404);
        }

        $category->update(['is_deleted' => true]);

        return response()->json(['message' => 'Category deleted successfully'], 200);
    }

    public function bulkDestroy(Request $request)
    {
        $validated = $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'exists:categories,id'
        ]);

        $ids = $validated['ids'];
        
        Category::whereIn('id', $ids)
            ->where('is_deleted', false)
            ->update(['is_deleted' => true]);

        return response()->json([
            'message' => count($ids) . ' categories deleted successfully'
        ], 200);
    }

    public function restore($id)
    {
        $category = Category::find($id);

        if (!$category || $category->is_deleted == false) {
            return response()->json(['message' => 'Category not found or not deleted'], 404);
        }

        $category->update(['is_deleted' => false]);

        return response()->json(['message' => 'Category restored successfully'], 200);
    }
}