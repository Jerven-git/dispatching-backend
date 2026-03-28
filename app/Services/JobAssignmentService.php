<?php

namespace App\Services;

use App\Models\ServiceJob;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class JobAssignmentService
{
    public function assign(ServiceJob $job, ?int $technicianId): ServiceJob
    {
        $data = ['technician_id' => $technicianId];

        if ($technicianId && $job->status === 'pending') {
            $data['status'] = 'assigned';
        }

        if (! $technicianId && $job->status === 'assigned') {
            $data['status'] = 'pending';
        }

        $job->update($data);
        $job->load(['customer', 'service', 'technician', 'creator']);

        return $job;
    }

    /**
     * Get workload summary for all active technicians.
     *
     * Returns each technician with counts of their active (non-terminal) jobs
     * and today's scheduled jobs.
     */
    public function getTechnicianWorkloads(): Collection
    {
        $technicians = User::where('role', 'technician')
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $activeJobCounts = ServiceJob::select('technician_id', DB::raw('count(*) as count'))
            ->whereNotNull('technician_id')
            ->whereNotIn('status', ['completed', 'cancelled'])
            ->groupBy('technician_id')
            ->pluck('count', 'technician_id');

        $todayJobCounts = ServiceJob::select('technician_id', DB::raw('count(*) as count'))
            ->whereNotNull('technician_id')
            ->whereNotIn('status', ['completed', 'cancelled'])
            ->whereDate('scheduled_date', today())
            ->groupBy('technician_id')
            ->pluck('count', 'technician_id');

        return $technicians->map(fn (User $tech) => [
            'id' => $tech->id,
            'name' => $tech->name,
            'email' => $tech->email,
            'phone' => $tech->phone,
            'active_jobs' => $activeJobCounts->get($tech->id, 0),
            'today_jobs' => $todayJobCounts->get($tech->id, 0),
        ]);
    }
}
