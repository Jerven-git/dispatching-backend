<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CustomerController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\JobEnhancementController;
use App\Http\Controllers\Api\NotificationController;
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

    // Job enhancements (all roles — authorization handled in controller)
    Route::prefix('service-jobs/{service_job}')->group(function () {
        Route::get('/attachments', [JobEnhancementController::class, 'attachments']);
        Route::post('/attachments', [JobEnhancementController::class, 'uploadAttachment']);
        Route::delete('/attachments/{attachment}', [JobEnhancementController::class, 'deleteAttachment']);
        Route::get('/checklist', [JobEnhancementController::class, 'checklist']);
        Route::patch('/checklist/{checklist_item}/toggle', [JobEnhancementController::class, 'toggleChecklistItem']);
        Route::post('/signature', [JobEnhancementController::class, 'storeSignature']);
        Route::get('/comments', [JobEnhancementController::class, 'comments']);
        Route::post('/comments', [JobEnhancementController::class, 'storeComment']);
        Route::delete('/comments/{comment}', [JobEnhancementController::class, 'deleteComment']);
    });

    // Notifications (all roles)
    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::get('/notifications/unread-count', [NotificationController::class, 'unreadCount']);
    Route::patch('/notifications/{id}/read', [NotificationController::class, 'markAsRead']);
    Route::post('/notifications/mark-all-read', [NotificationController::class, 'markAllAsRead']);

    // Admin & Dispatcher routes
    Route::middleware('role:admin,dispatcher')->group(function () {
        Route::apiResource('customers', CustomerController::class);
        Route::get('/service-jobs/calendar', [ServiceJobController::class, 'calendar']);
        Route::apiResource('service-jobs', ServiceJobController::class)->except(['destroy']);
        Route::patch('/service-jobs/{service_job}/status', [ServiceJobController::class, 'updateStatus']);
        Route::patch('/service-jobs/{service_job}/assign', [ServiceJobController::class, 'assignTechnician']);
        Route::post('/service-jobs/{service_job}/clone', [JobEnhancementController::class, 'cloneJob']);
        Route::get('/technicians/workloads', [ServiceJobController::class, 'technicianWorkloads']);
        Route::get('/users/technicians', [UserController::class, 'technicians']);

        // Services (read-only for dispatchers)
        Route::get('/services', [ServiceController::class, 'index']);
        Route::get('/services/{service}', [ServiceController::class, 'show']);

        // Reports
        Route::get('/reports/summary', [ReportController::class, 'summary']);
        Route::get('/reports/jobs-by-status', [ReportController::class, 'jobsByStatus']);
        Route::get('/reports/jobs-by-date', [ReportController::class, 'jobsByDate']);
        Route::get('/reports/technician-performance', [ReportController::class, 'technicianPerformance']);
    });

    // Admin-only routes
    Route::middleware('role:admin')->group(function () {
        Route::apiResource('services', ServiceController::class)->except(['index', 'show']);
        Route::get('/services/{service}/checklist', [JobEnhancementController::class, 'checklistItems']);
        Route::post('/services/{service}/checklist', [JobEnhancementController::class, 'storeChecklistItem']);
        Route::delete('/services/{service}/checklist/{checklist_item}', [JobEnhancementController::class, 'deleteChecklistItem']);
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
        Route::get('/my-jobs/{service_job}', [ServiceJobController::class, 'showMyJob']);
        Route::patch('/my-jobs/{service_job}/status', [ServiceJobController::class, 'updateMyJobStatus']);
    });
});
