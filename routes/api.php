<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\ShipRouteController;

Route::apiResource('categories', CategoryController::class);
Route::apiResource('ship-routes', ShipRouteController::class);