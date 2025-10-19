<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\ShipRouteController;

// Category CRUD + restore
Route::apiResource('categories', CategoryController::class);

// Add a custom route for restoring a soft-deleted category
Route::patch('categories/{id}/restore', [CategoryController::class, 'restore']);

// Other resources
Route::apiResource('ship-routes', ShipRouteController::class);
