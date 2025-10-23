<?php
// app/Http/Controllers/CategoryController.php

namespace App\Http\Controllers;

use App\Models\ShipRoute;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ShipRouteController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $search = $request->query('search');
        $sort = $request->query('sort', 'id');
        $direction = $request->query('direction', 'asc');
        $perPage = (int) $request->query('per_page', 10);

        $query = Category::select(['id', 'name', 'base_rate']);

        // Search
        if ($search) {
            $query->where('name', 'like', $search . '%');
        }

        // Validate and sanitize sort field
        $allowedSorts = ['id', 'name', 'base_rate'];
        if (!in_array($sort, $allowedSorts)) {
            $sort = 'id';
        }

        // Validate direction
        $direction = strtolower($direction) === 'desc' ? 'desc' : 'asc';

        return response()->json(
            $query->orderBy($sort, $direction)->paginate($perPage)
        );
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:categories,name',
            'base_rate' => 'required|numeric|min:0|max:999999.99',
        ]);

        $category = Category::create($validated);

        return response()->json($category, 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Category $category)
    {
        return response()->json($category);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Category $category)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:categories,name,' . $category->id,
            'base_rate' => 'required|numeric|min:0|max:999999.99',
        ]);

        $category->update($validated);

        return response()->json($category);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Category $category)
    {
        $category->delete();

        return response()->json(null, 204);
    }
}