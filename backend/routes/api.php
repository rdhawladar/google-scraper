<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\KeywordController;
use App\Http\Controllers\SearchResultController;
use App\Http\Controllers\ScraperAnalyticsController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// Auth routes
Route::post('/login', [AuthController::class, 'login']);
Route::post('/register', [AuthController::class, 'register']);

// Protected routes
Route::middleware('auth:sanctum')->group(function () {
    // User routes
    Route::get('/user', [AuthController::class, 'user']);
    Route::post('/logout', [AuthController::class, 'logout']);

    // Keywords routes
    Route::get('/keywords', [KeywordController::class, 'index']);
    Route::post('/keywords/upload', [KeywordController::class, 'upload']);
    Route::get('/keywords/{keyword}', [KeywordController::class, 'show']);
    Route::delete('/keywords/{keyword}', [KeywordController::class, 'destroy']);
    Route::post('/keywords/{keyword}/retry', [KeywordController::class, 'retry']);

    // Search Results routes
    Route::get('/search-results/{keyword}', [SearchResultController::class, 'show']);

    // Analytics routes
    Route::prefix('analytics')->group(function () {
        Route::get('/stats', [ScraperAnalyticsController::class, 'getStats']);
        Route::get('/hourly', [ScraperAnalyticsController::class, 'getHourlyStats']);
        Route::get('/failures', [ScraperAnalyticsController::class, 'getFailureAnalysis']);
        Route::get('/keywords', [ScraperAnalyticsController::class, 'getKeywordStats']);
    });
});
