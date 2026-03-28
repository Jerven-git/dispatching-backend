<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CustomerController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\ServiceController;
use App\Http\Controllers\Api\ServiceJobController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Support\Facades\Route;

// Public routes
Route::post('/login', [AuthController::class, 'login'])
    ->middleware('throttle:5,1'); // 5 attempts per minute

// Authenticated routes
Route::middleware('auth:sanctum')->group(function () {
    // Auth
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);

    // Dashboard (all roles, scoped in controller)
    Route::get('/dashboard', [DashboardController::class, 'index']);

    // Admin & Dispatcher routes
    Route::middleware('role:admin,dispatcher')->group(function () {
        Route::apiResource('customers', CustomerController::class);
        Route::apiResource('service-jobs', ServiceJobController::class)->except(['destroy']);
        Route::patch('/service-jobs/{service_job}/status', [ServiceJobController::class, 'updateStatus']);
        Route::patch('/service-jobs/{service_job}/assign', [ServiceJobController::class, 'assignTechnician']);
        Route::get('/technicians/workloads', [ServiceJobController::class, 'technicianWorkloads']);
        Route::get('/users/technicians', [UserController::class, 'technicians']);

        // Reports
        Route::get('/reports/summary', [ReportController::class, 'summary']);
        Route::get('/reports/jobs-by-status', [ReportController::class, 'jobsByStatus']);
        Route::get('/reports/jobs-by-date', [ReportController::class, 'jobsByDate']);
        Route::get('/reports/technician-performance', [ReportController::class, 'technicianPerformance']);
    });

    // Admin-only routes
    Route::middleware('role:admin')->group(function () {
        Route::apiResource('services', ServiceController::class);
        Route::delete('/service-jobs/{service_job}', [ServiceJobController::class, 'destroy']);
        Route::get('/users', [UserController::class, 'index']);
        Route::post('/users', [UserController::class, 'store']);
        Route::get('/users/{user}', [UserController::class, 'show']);
        Route::put('/users/{user}', [UserController::class, 'update']);
        Route::delete('/users/{user}', [UserController::class, 'destroy']);
    });

    // Technician routes
    Route::middleware('role:technician')->group(function () {
        Route::get('/my-jobs', [ServiceJobController::class, 'myJobs']);
        Route::patch('/my-jobs/{service_job}/status', [ServiceJobController::class, 'updateMyJobStatus']);
    });
});
