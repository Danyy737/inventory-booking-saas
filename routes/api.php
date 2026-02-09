<?php

use App\Http\Controllers\Api\MeController;
use App\Http\Controllers\Api\InventoryController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\BookingController;
use App\Http\Controllers\Api\PackageController;

Route::middleware(['auth:sanctum', 'tenant'])->group(function () {

    Route::get('/me', MeController::class);

    // Inventory
    Route::get('/inventory/items', [InventoryController::class, 'index']);
    Route::post('/inventory/items', [InventoryController::class, 'store']);
    Route::patch('/inventory/items/{id}', [InventoryController::class, 'update']);
    Route::delete('/inventory/items/{id}', [InventoryController::class, 'destroy']);
    Route::post('/inventory/check-availability', [InventoryController::class, 'checkAvailability']);

    // Bookings
    Route::get('/bookings', [BookingController::class, 'index']);
    Route::get('/bookings/{id}', [BookingController::class, 'show']);
    Route::post('/bookings', [BookingController::class, 'store']);
    Route::patch('/bookings/{id}', [BookingController::class, 'update']);
    Route::patch('/bookings/{id}/cancel', [BookingController::class, 'cancel']);

    // Packages
    Route::get('/packages', [PackageController::class, 'index']);
    Route::post('/packages', [PackageController::class, 'store']);
    Route::patch('/packages/{id}', [PackageController::class, 'update']);
    Route::get('/packages/{id}', [PackageController::class, 'show']);
    Route::put('/packages/{id}/items', [PackageController::class, 'updateItems']);
    Route::post('/packages/check-availability', [PackageController::class, 'checkAvailability']);
});

// Public / internal health check (keep public)
Route::get('/health', fn () => response()->json(['ok' => true]));

