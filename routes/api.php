<?php

use App\Http\Controllers\Api\MeController;
use App\Http\Controllers\Api\InventoryController;
use App\Http\Controllers\Api\BookingController;
use App\Http\Controllers\Api\PackageController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\MyOrganisationsController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\OrganisationOnboardingController;
use App\Http\Controllers\Api\OrganisationMembersController;

/*
|--------------------------------------------------------------------------
| PUBLIC ROUTES
|--------------------------------------------------------------------------
*/

Route::post('/auth/login', [AuthController::class, 'login']);
Route::get('/health', fn () => response()->json(['ok' => true]));
Route::get('/ping', fn () => response()->json(['ok' => true]));
Route::post('/auth/register', [AuthController::class, 'register']);

/*
|--------------------------------------------------------------------------
| AUTH-ONLY ROUTES (NO TENANT REQUIRED)
|--------------------------------------------------------------------------
*/

Route::middleware('auth:sanctum')->group(function () {

    // Identity
    Route::get('/me', [MeController::class, 'show']);

    // Organisation bootstrap
    Route::get('/my/organisations', [MyOrganisationsController::class, 'index']);
    Route::post('/me/select-organisation', [MeController::class, 'selectOrganisation']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    
    
        // Onboarding: create/join organisation
    Route::post('/organisations', [OrganisationOnboardingController::class, 'store']);
    Route::post('/organisations/join', [OrganisationOnboardingController::class, 'join']);

});


/*
|--------------------------------------------------------------------------
| TENANT ROUTES (AUTH + TENANT REQUIRED)
|--------------------------------------------------------------------------
*/

Route::middleware(['auth:sanctum', 'tenant'])->group(function () {

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
    Route::get('/bookings/{booking}/packing-list', [BookingController::class, 'packingList']);
    Route::post('/bookings/preview-availability', [BookingController::class, 'previewAvailability']);

    // Packages
    Route::get('/packages', [PackageController::class, 'index']);
    Route::post('/packages', [PackageController::class, 'store']);
    Route::patch('/packages/{id}', [PackageController::class, 'update']);
    Route::get('/packages/{id}', [PackageController::class, 'show']);
    Route::put('/packages/{id}/items', [PackageController::class, 'updateItems']);
    Route::post('/packages/check-availability', [PackageController::class, 'checkAvailability']);

    Route::get('/organisations/members', [OrganisationMembersController::class, 'index']);

});
