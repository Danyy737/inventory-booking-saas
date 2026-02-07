<?php

use App\Http\Controllers\Api\MeController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->get('/me', MeController::class);
Route::get('/health', fn () => response()->json(['ok' => true]));
