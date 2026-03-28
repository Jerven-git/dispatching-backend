<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\ServiceJob;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $jobQuery = ServiceJob::query();

        // Technicians only see their own stats
        if ($user->isTechnician()) {
            $jobQuery->where('technician_id', $user->id);
        }

        $stats = [
            'total_jobs' => (clone $jobQuery)->count(),
            'pending_jobs' => (clone $jobQuery)->where('status', 'pending')->count(),
            'assigned_jobs' => (clone $jobQuery)->where('status', 'assigned')->count(),
            'in_progress_jobs' => (clone $jobQuery)->where('status', 'in_progress')->count(),
            'completed_jobs' => (clone $jobQuery)->where('status', 'completed')->count(),
            'cancelled_jobs' => (clone $jobQuery)->where('status', 'cancelled')->count(),
        ];

        if (! $user->isTechnician()) {
            $stats['total_customers'] = Customer::count();
            $stats['total_technicians'] = User::where('role', 'technician')->where('is_active', true)->count();
        }

        $stats['todays_jobs'] = (clone $jobQuery)->where('scheduled_date', today())->count();
        $stats['completed_today'] = (clone $jobQuery)->where('status', 'completed')
            ->whereDate('completed_at', today())->count();

        return response()->json(['stats' => $stats]);
    }
}
