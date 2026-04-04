<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CustomerController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\InvoiceController;
use App\Http\Controllers\Api\JobEnhancementController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\Portal\CustomerAuthController;
use App\Http\Controllers\Api\Portal\CustomerPortalController;
use App\Http\Controllers\Api\AnalyticsController;
use App\Http\Controllers\Api\AuditLogController;
use App\Http\Controllers\Api\RoleController;
use App\Http\Controllers\Api\TenantController;
use App\Http\Controllers\Api\ExportController;
use App\Http\Controllers\Api\JobPartController;
use App\Http\Controllers\Api\PartController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\RouteController;
use App\Http\Controllers\Api\ScheduledReportController;
use App\Http\Controllers\Api\ServiceController;
use App\Http\Controllers\Api\ServiceJobController;
use App\Http\Controllers\Api\ServiceRequestController;
use App\Http\Controllers\Api\SyncController;
use App\Http\Controllers\Api\TechnicianLocationController;
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

        // Job parts (all roles — authorization handled contextually)
        Route::get('/parts', [JobPartController::class, 'index']);
        Route::post('/parts', [JobPartController::class, 'store']);
        Route::put('/parts/{part}', [JobPartController::class, 'update']);
        Route::delete('/parts/{part}', [JobPartController::class, 'destroy']);
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

        // Invoices
        Route::get('/invoices', [InvoiceController::class, 'index']);
        Route::get('/invoices/{invoice}', [InvoiceController::class, 'show']);
        Route::post('/service-jobs/{service_job}/invoice', [InvoiceController::class, 'generate']);
        Route::put('/invoices/{invoice}', [InvoiceController::class, 'update']);
        Route::patch('/invoices/{invoice}/paid', [InvoiceController::class, 'markAsPaid']);
        Route::patch('/invoices/{invoice}/sent', [InvoiceController::class, 'markAsSent']);
        Route::get('/invoices/{invoice}/pdf', [InvoiceController::class, 'downloadPdf']);
        Route::delete('/invoices/{invoice}', [InvoiceController::class, 'destroy']);

        // Service Requests (from customer portal)
        Route::get('/service-requests', [ServiceRequestController::class, 'index']);
        Route::post('/service-requests/{service_request}/approve', [ServiceRequestController::class, 'approve']);
        Route::post('/service-requests/{service_request}/decline', [ServiceRequestController::class, 'decline']);

        // Reports
        Route::get('/reports/summary', [ReportController::class, 'summary']);
        Route::get('/reports/jobs-by-status', [ReportController::class, 'jobsByStatus']);
        Route::get('/reports/jobs-by-date', [ReportController::class, 'jobsByDate']);
        Route::get('/reports/technician-performance', [ReportController::class, 'technicianPerformance']);

        // Analytics
        Route::get('/analytics/revenue-trend', [AnalyticsController::class, 'revenueTrend']);
        Route::get('/analytics/job-trend', [AnalyticsController::class, 'jobTrend']);
        Route::get('/analytics/service-popularity', [AnalyticsController::class, 'servicePopularity']);
        Route::get('/analytics/customer-lifetime-value', [AnalyticsController::class, 'customerLifetimeValue']);
        Route::get('/analytics/job-profitability', [AnalyticsController::class, 'jobProfitability']);

        // CSV Exports
        Route::get('/export/jobs', [ExportController::class, 'jobs']);
        Route::get('/export/invoices', [ExportController::class, 'invoices']);
        Route::get('/export/customers', [ExportController::class, 'customers']);
        Route::get('/export/technician-performance', [ExportController::class, 'technicianPerformance']);

        // Inventory & Parts
        Route::get('/parts', [PartController::class, 'index']);
        Route::get('/parts/low-stock', [PartController::class, 'lowStock']);
        Route::get('/parts/{part}', [PartController::class, 'show']);

        // Field Operations — GPS & Routing
        Route::get('/technicians/locations', [TechnicianLocationController::class, 'index']);
        Route::get('/technicians/{technician}/location', [TechnicianLocationController::class, 'show']);
        Route::get('/technicians/{technician}/route', [RouteController::class, 'show']);
        Route::get('/service-jobs/{service_job}/eta', [ServiceJobController::class, 'eta']);
    });

    // Admin-only routes
    Route::middleware('role:admin')->group(function () {
        // Parts catalog management
        Route::post('/parts', [PartController::class, 'store']);
        Route::put('/parts/{part}', [PartController::class, 'update']);
        Route::delete('/parts/{part}', [PartController::class, 'destroy']);
        Route::post('/parts/{part}/adjust-stock', [PartController::class, 'adjustStock']);

        // Tenants (super-admin)
        Route::apiResource('tenants', TenantController::class);

        // Roles & Permissions
        Route::get('/roles', [RoleController::class, 'index']);
        Route::post('/roles', [RoleController::class, 'store']);
        Route::get('/roles/{role}', [RoleController::class, 'show']);
        Route::put('/roles/{role}', [RoleController::class, 'update']);
        Route::delete('/roles/{role}', [RoleController::class, 'destroy']);
        Route::get('/permissions', [RoleController::class, 'permissions']);

        // Audit Logs
        Route::get('/audit-logs', [AuditLogController::class, 'index']);
        Route::get('/audit-logs/{audit_log}', [AuditLogController::class, 'show']);

        // Scheduled Reports
        Route::get('/scheduled-reports', [ScheduledReportController::class, 'index']);
        Route::post('/scheduled-reports', [ScheduledReportController::class, 'store']);
        Route::get('/scheduled-reports/{scheduled_report}', [ScheduledReportController::class, 'show']);
        Route::put('/scheduled-reports/{scheduled_report}', [ScheduledReportController::class, 'update']);
        Route::delete('/scheduled-reports/{scheduled_report}', [ScheduledReportController::class, 'destroy']);

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

        // Field Operations
        Route::post('/my-location', [TechnicianLocationController::class, 'store']);
        Route::get('/my-route', [RouteController::class, 'myRoute']);
        Route::get('/my-jobs/{service_job}/eta', [ServiceJobController::class, 'eta']);
        Route::post('/sync', [SyncController::class, 'sync']);
    });
});

// ── Customer Portal ─────────────────────────────────────────────
Route::prefix('portal')->group(function () {
    Route::post('/login', [CustomerAuthController::class, 'login'])
        ->middleware('throttle:5,1');

    Route::middleware('auth:customer')->group(function () {
        Route::get('/me', [CustomerAuthController::class, 'me']);
        Route::post('/logout', [CustomerAuthController::class, 'logout']);

        // My Jobs
        Route::get('/jobs', [CustomerPortalController::class, 'myJobs']);
        Route::get('/jobs/{job}', [CustomerPortalController::class, 'showJob']);
        Route::get('/jobs/{job}/eta', [CustomerPortalController::class, 'jobEta']);

        // My Invoices
        Route::get('/invoices', [CustomerPortalController::class, 'myInvoices']);
        Route::get('/invoices/{invoice}', [CustomerPortalController::class, 'showInvoice']);
        Route::get('/invoices/{invoice}/pdf', [CustomerPortalController::class, 'downloadInvoice']);

        // Request Service
        Route::get('/services', [CustomerPortalController::class, 'availableServices']);
        Route::post('/service-requests', [CustomerPortalController::class, 'submitRequest']);
        Route::get('/service-requests', [CustomerPortalController::class, 'myRequests']);

        // Reviews
        Route::post('/jobs/{job}/review', [CustomerPortalController::class, 'submitReview']);
        Route::get('/reviews', [CustomerPortalController::class, 'myReviews']);
    });
});
