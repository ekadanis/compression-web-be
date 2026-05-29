<?php

use App\Http\Controllers\YoutubeOAuthController;
use App\Http\Controllers\SoundCloudOAuthController;
use Illuminate\Support\Facades\Route;

Route::get('/youtube/auth/callback', [YoutubeOAuthController::class, 'callback']);
Route::get('/api/youtube/callback', [YoutubeOAuthController::class, 'callback']);
Route::get('/soundcloud/auth/callback', [SoundCloudOAuthController::class, 'callback']);
Route::get('/api/soundcloud/callback', [SoundCloudOAuthController::class, 'callback']);
