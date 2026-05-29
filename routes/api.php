<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CompressionController;
use App\Http\Controllers\Api\FileController;
use App\Http\Controllers\Api\SoundCloudController;
use App\Http\Controllers\Api\YoutubeController;
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

// Public download route
Route::get('/compressions/{id}/download', [CompressionController::class, 'download']);
Route::get('/compressions/{id}/stream', [CompressionController::class, 'stream']);

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

    // YouTube
    Route::prefix('youtube')->group(function () {
        Route::get('/account', [YoutubeController::class, 'account']);
        Route::get('/auth/redirect', [YoutubeController::class, 'authRedirect']);
        Route::post('/auth/disconnect', [YoutubeController::class, 'disconnect']);
        Route::get('/sources', [YoutubeController::class, 'sources']);
        Route::get('/uploads', [YoutubeController::class, 'uploads']);
        Route::post('/uploads', [YoutubeController::class, 'store']);
        Route::get('/uploads/{upload}', [YoutubeController::class, 'show']);
        Route::delete('/uploads/{upload}', [YoutubeController::class, 'destroy']);
    });

    // SoundCloud
    Route::prefix('soundcloud')->group(function () {
        Route::get('/account', [SoundCloudController::class, 'account']);
        Route::get('/auth/redirect', [SoundCloudController::class, 'authRedirect']);
        Route::post('/auth/disconnect', [SoundCloudController::class, 'disconnect']);
        Route::get('/sources', [SoundCloudController::class, 'sources']);
        Route::get('/uploads', [SoundCloudController::class, 'uploads']);
        Route::post('/uploads', [SoundCloudController::class, 'store']);
        Route::get('/uploads/{upload}', [SoundCloudController::class, 'show']);
        Route::delete('/uploads/{upload}', [SoundCloudController::class, 'destroy']);
    });
});
