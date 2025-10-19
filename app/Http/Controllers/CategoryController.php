<?php

namespace App\Http\Controllers;

use App\Models\Category;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    /**
     * Display a paginated listing of the resource.
     */
    public function index(Request $request)
    {
        $perPage = $request->get('per_page', 10);
        $search = $request->get('search', '');

        $query = Category::where('is_deleted', 0);

        if (!empty($search)) {
            $query->where('name', 'like', '%' . $search . '%');
        }

        // Optional sorting
        $sort = $request->get('sort', 'id');
        $direction = $request->get('direction', 'asc');

        $categories = $query->orderBy($sort, $direction)->paginate($perPage);

        return response()->json($categories);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'base_rate' => 'required|numeric',
        ]);

        $category = Category::create(array_merge($validated, ['is_deleted' => 0]));

        return response()->json($category, 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Category $category)
    {
        if ($category->is_deleted) {
            return response()->json(['message' => 'Category not found'], 404);
        }

        return response()->json($category);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Category $category)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'base_rate' => 'required|numeric',
        ]);

        $category->update($validated);

        return response()->json($category);
    }

    /**
     * Soft delete the specified resource.
     */
    public function destroy(Category $category)
    {
        $category->update(['is_deleted' => 1]);

        return response()->json(['message' => 'Category marked as deleted'], 200);
    }

    /**
     * Restore a soft-deleted category.
     */
    public function restore($id)
    {
        $category = Category::find($id);

        if (!$category || $category->is_deleted == 0) {
            return response()->json(['message' => 'Category not found or not deleted'], 404);
        }

        $category->update(['is_deleted' => 0]);

        return response()->json(['message' => 'Category restored successfully'], 200);
    }
}
