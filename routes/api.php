<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\ShipRouteController;

// Remove the middleware group - apply CORS globally instead
Route::apiResource('categories', CategoryController::class);
Route::apiResource('ship-routes', ShipRouteController::class);