<?php

use App\Http\Controllers\Api\MeController;
use App\Http\Controllers\Api\InventoryController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\BookingController;

Route::middleware('auth:sanctum')->group(function () {
    // Auth / session boundary
    Route::get('/me', MeController::class);

    // Inventory (organisation-scoped)
    Route::get('/inventory/items', [InventoryController::class, 'index']);
    Route::post('/inventory/items', [InventoryController::class, 'store']);
});

// Public / internal health check
Route::get('/health', fn () => response()->json(['ok' => true]));

// Edit Inventory
Route::patch('/inventory/items/{id}', [InventoryController::class, 'update']);

// Delete Inventory
Route::delete('/inventory/items/{id}', [InventoryController::class, 'destroy']);


//Available During Start and End
Route::post('/inventory/check-availability', [InventoryController::class, 'checkAvailability']);

//Bookings
Route::post('/bookings', [BookingController::class, 'store']);

