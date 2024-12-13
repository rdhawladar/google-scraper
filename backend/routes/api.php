<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\KeywordController;

// Public routes
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

// Protected routes
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'user']);
    
    // Keyword routes
    Route::get('/keywords', [KeywordController::class, 'index']);
    Route::get('/keywords/{keyword}', [KeywordController::class, 'show']);
    Route::post('/keywords/upload', [KeywordController::class, 'upload']);
    Route::post('/keywords/{keyword}/retry', [KeywordController::class, 'retry']);
});
