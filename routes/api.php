<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\ShipRouteController;

// Category CRUD + restore
Route::apiResource('categories', CategoryController::class);

// Add a custom route for restoring a soft-deleted category
Route::patch('categories/{id}/restore', [CategoryController::class, 'restore']);

// Add bulk delete route
Route::post('categories/bulk-delete', [CategoryController::class, 'bulkDestroy']);

// Other resources
Route::apiResource('ship-routes', ShipRouteController::class);