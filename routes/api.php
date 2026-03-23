<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CustomerController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\ServiceController;
use App\Http\Controllers\Api\ServiceJobController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Support\Facades\Route;

// Public routes
Route::post('/login', [AuthController::class, 'login']);

// Protected routes
Route::middleware('auth:sanctum')->group(function () {
    // Auth
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);

    // Dashboard
    Route::get('/dashboard', [DashboardController::class, 'index']);

    // Customers
    Route::apiResource('customers', CustomerController::class);

    // Services
    Route::apiResource('services', ServiceController::class);

    // Service Jobs
    Route::patch('/service-jobs/{service_job}/status', [ServiceJobController::class, 'updateStatus']);
    Route::apiResource('service-jobs', ServiceJobController::class);

    // Users
    Route::get('/users', [UserController::class, 'index']);
    Route::get('/users/technicians', [UserController::class, 'technicians']);
    Route::get('/users/{user}', [UserController::class, 'show']);
});
