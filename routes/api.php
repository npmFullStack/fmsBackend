<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\ContainerTypeController;
use App\Http\Controllers\ItemController;
use App\Http\Controllers\PortController;
use App\Http\Controllers\TruckCompController;
use App\Http\Controllers\ShipRouteController;
use App\Http\Controllers\ShippingLineController;
use App\Http\Controllers\BookingController;
use App\Http\Controllers\UserController;


Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');
    Route::get('/user', [AuthController::class, 'user'])->middleware('auth:sanctum');
});


// Category Route Group
Route::prefix('categories')->group(function () {
    Route::get('/', [CategoryController::class, 'index']);
    Route::post('/', [CategoryController::class, 'store']);
    Route::get('/{id}', [CategoryController::class, 'show']);
    Route::put('/{id}', [CategoryController::class, 'update']);
    Route::delete('/{id}', [CategoryController::class, 'destroy']);
    Route::post('/bulk-delete', [CategoryController::class, 'bulkDestroy']);
    Route::post('/{id}/restore', [CategoryController::class, 'restore']);
});

// Container Type Route Group
Route::prefix('container-types')->group(function () {
    Route::get('/', [ContainerTypeController::class, 'index']);
    Route::post('/', [ContainerTypeController::class, 'store']);
    Route::get('/{id}', [ContainerTypeController::class, 'show']);
    Route::put('/{id}', [ContainerTypeController::class, 'update']);
    Route::delete('/{id}', [ContainerTypeController::class, 'destroy']);
    Route::post('/bulk-delete', [ContainerTypeController::class, 'bulkDestroy']);
    Route::post('/{id}/restore', [ContainerTypeController::class, 'restore']);
});


// Ports Route Group
Route::prefix('ports')->group(function () {
    Route::get('/', [PortController::class, 'index']);
    Route::post('/', [PortController::class, 'store']);
    Route::get('/{id}', [PortController::class, 'show']);
    Route::put('/{id}', [PortController::class, 'update']);
    Route::delete('/{id}', [PortController::class, 'destroy']);
    Route::post('/bulk-delete', [PortController::class, 'bulkDestroy']);
    Route::post('/{id}/restore', [PortController::class, 'restore']);
});



//  Truck Companies Route Group
Route::prefix('truck-comps')->group(function () {
    Route::get('/', [TruckCompController::class, 'index']);
    Route::post('/', [TruckCompController::class, 'store']);
    Route::get('/{id}', [TruckCompController::class, 'show']);
    Route::put('/{id}', [TruckCompController::class, 'update']);
    Route::delete('/{id}', [TruckCompController::class, 'destroy']);
    Route::post('/bulk-delete', [TruckCompController::class, 'bulkDestroy']);
    Route::post('/{id}/restore', [TruckCompController::class, 'restore']);
});

// Shipping Lines Route Group
Route::prefix('shipping-lines')->group(function () {
    Route::get('/', [ShippingLineController::class, 'index']);
    Route::post('/', [ShippingLineController::class, 'store']);
    Route::get('/{id}', [ShippingLineController::class, 'show']);
    Route::put('/{id}', [ShippingLineController::class, 'update']);
    Route::delete('/{id}', [ShippingLineController::class, 'destroy']);
    Route::post('/bulk-delete', [ShippingLineController::class, 'bulkDestroy']);
    Route::post('/{id}/restore', [ShippingLineController::class, 'restore']);
    Route::get('/{id}/routes', [ShippingLineController::class, 'getRoutes']);
});

// Ship Routes Route Group
Route::prefix('ship-routes')->group(function () {
    Route::get('/', [ShipRouteController::class, 'index']);
    Route::post('/', [ShipRouteController::class, 'store']);
    Route::get('/{id}', [ShipRouteController::class, 'show']);
    Route::put('/{id}', [ShipRouteController::class, 'update']);
    Route::delete('/{id}', [ShipRouteController::class, 'destroy']);
    Route::post('/bulk-delete', [ShipRouteController::class, 'bulkDestroy']);
    Route::post('/{id}/restore', [ShipRouteController::class, 'restore']);
    Route::get('/find/route', [ShipRouteController::class, 'getRouteBetweenPorts']);
});

// Bookings Route Group
Route::prefix('bookings')->group(function () {
    Route::get('/', [BookingController::class, 'index']);
    Route::post('/', [BookingController::class, 'store']); // For regular bookings with user
    Route::post('/quote', [BookingController::class, 'quote']); // For quote requests
    Route::get('/{id}', [BookingController::class, 'show']);
    Route::put('/{id}', [BookingController::class, 'update']);
    Route::delete('/{id}', [BookingController::class, 'destroy']);
    Route::post('/bulk-delete', [BookingController::class, 'bulkDestroy']);
    Route::post('/{id}/restore', [BookingController::class, 'restore']);
    // Add approval route
    Route::post('/{id}/approve', [BookingController::class, 'approveBooking']);
});

// Users Route Group
Route::prefix('users')->group(function () {
    Route::get('/', [UserController::class, 'index']);
    Route::post('/', [UserController::class, 'store']);
    Route::get('/{id}', [UserController::class, 'show']);
    Route::put('/{id}', [UserController::class, 'update']);
    Route::delete('/{id}', [UserController::class, 'destroy']);
    Route::post('/bulk-delete', [UserController::class, 'bulkDestroy']);
    Route::post('/{id}/restore', [UserController::class, 'restore']);
    Route::post('/{id}/promote', [UserController::class, 'promote']);
});