<?php
// app/Http/Controllers/CategoryController.php

namespace App\Http\Controllers;

use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CategoryController extends Controller
{
    /**
     * Display a listing of the resource.
     * Supports search, sort, pagination, and optimized caching.
     */
    public function index(Request $request)
    {
        $search = $request->query('search');
        $sort = $request->query('sort', 'id');
        $direction = $request->query('direction', 'asc');
        $perPage = (int) $request->query('per_page', 10);

        // Build optimized cache key
        $cacheKey = sprintf(
            'categories:%s:%s:%s:%d:%d',
            $search ?? 'all',
            $sort,
            $direction,
            $perPage,
            $request->query('page', 1)
        );

        // Cache for 5 minutes
        $categories = Cache::remember($cacheKey, 300, function () use ($search, $sort, $direction, $perPage) {
            $query = Category::select(['id', 'name', 'base_rate']);

            // Search optimization - use prefix matching for better index usage
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

            return $query->orderBy($sort, $direction)
                        ->paginate($perPage);
        });

        return response()->json($categories);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'name' => 'required|string|max:255|unique:categories,name',
                'base_rate' => 'required|numeric|min:0|max:999999.99',
            ]);

            DB::beginTransaction();

            $category = Category::create($validated);

            // Clear only category-related cache keys
            $this->clearCategoryCache();

            DB::commit();

            return response()->json($category, 201);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to create category'
            ], 500);
        }
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
        try {
            $validated = $request->validate([
                'name' => 'sometimes|required|string|max:255|unique:categories,name,' . $category->id,
                'base_rate' => 'sometimes|required|numeric|min:0|max:999999.99',
            ]);

            DB::beginTransaction();

            $category->update($validated);

            // Clear only category-related cache keys
            $this->clearCategoryCache();

            DB::commit();

            return response()->json($category);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to update category'
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Category $category)
    {
        try {
            DB::beginTransaction();

            $category->delete();

            // Clear only category-related cache keys
            $this->clearCategoryCache();

            DB::commit();

            return response()->json(null, 204);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to delete category'
            ], 500);
        }
    }

    /**
     * Clear only category-related cache keys instead of flushing entire cache
     */
    private function clearCategoryCache()
    {
        // Get all cache keys that start with 'categories:'
        $keys = Cache::getStore()->getRedis()->keys('*categories:*');
        
        if ($keys) {
            foreach ($keys as $key) {
                // Remove the prefix that Redis adds
                $cleanKey = str_replace(config('database.redis.options.prefix'), '', $key);
                Cache::forget($cleanKey);
            }
        }
    }
}