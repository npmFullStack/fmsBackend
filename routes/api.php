<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\ShipRouteController;

// IMPORTANT: Define custom routes BEFORE apiResource
// Add bulk delete route (must be before apiResource)
Route::post('categories/bulk-delete', [CategoryController::class, 'bulkDestroy']);

// Add restore route (must be before apiResource)
Route::patch('categories/{id}/restore', [CategoryController::class, 'restore']);

// Category CRUD
Route::apiResource('categories', CategoryController::class);

// Other resources
Route::apiResource('ship-routes', ShipRouteController::class);