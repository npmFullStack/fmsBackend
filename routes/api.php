<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\ContainerTypeController;
use App\Http\Controllers\ItemController;
use App\Http\Controllers\PortController;

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
// Items Route Group
Route::prefix('items')->group(function () {
    Route::get('/', [ItemController::class, 'index']);
    Route::post('/', [ItemController::class, 'store']);
    Route::get('/{id}', [ItemController::class, 'show']);
    Route::put('/{id}', [ItemController::class, 'update']);
    Route::delete('/{id}', [ItemController::class, 'destroy']);
    Route::post('/bulk-delete', [ItemController::class, 'bulkDestroy']);
    Route::post('/{id}/restore', [ItemController::class, 'restore']);
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