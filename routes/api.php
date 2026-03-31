<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CompressionController;
use App\Http\Controllers\Api\FileController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// Public auth routes
Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login',    [AuthController::class, 'login']);
});

// Authenticated routes
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/auth/me',      [AuthController::class, 'me']);

    // Files
    Route::apiResource('files', FileController::class)->only(['index', 'store', 'show', 'destroy']);

    // Compressions
    Route::get('/compressions/compare', [CompressionController::class, 'compare']);
    Route::get('/compressions/{id}',    [CompressionController::class, 'show']);
    Route::get('/compressions',         [CompressionController::class, 'index']);
    Route::post('/compressions',        [CompressionController::class, 'store']);
    Route::delete('/compressions/{id}', [CompressionController::class, 'destroy']);
});
