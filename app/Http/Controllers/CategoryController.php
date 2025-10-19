<?php

namespace App\Http\Controllers;

use App\Models\Category;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        // Only non-deleted categories are automatically returned due to global scope
        return response()->json(Category::all());
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

        $category = Category::create($validated);

        return response()->json($category, 201);
    }

    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        $category = Category::findOrFail($id);
        return response()->json($category);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        $category = Category::findOrFail($id);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'base_rate' => 'required|numeric',
        ]);

        $category->update($validated);

        return response()->json($category);
    }

    /**
     * Soft delete — mark as deleted instead of removing.
     */
    public function destroy($id)
    {
        $category = Category::findOrFail($id);
        $category->update(['is_deleted' => 1]);

        return response()->json(['message' => 'Category marked as deleted'], 200);
    }

    /**
     * Optional: Restore a deleted category
     */
    public function restore($id)
    {
        $category = Category::withoutGlobalScope('not_deleted')->findOrFail($id);
        $category->update(['is_deleted' => 0]);

        return response()->json(['message' => 'Category restored'], 200);
    }
}
