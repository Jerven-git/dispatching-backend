<?php

namespace App\Services;

use App\Models\ServiceJob;
use Illuminate\Support\Facades\DB;

class ReportService
{
    public function getSummary(?string $from = null, ?string $to = null): array
    {
        $query = ServiceJob::query();
        $this->applyDateRange($query, $from, $to);

        $statusCounts = (clone $query)
            ->select('status', DB::raw('count(*) as count'))
            ->groupBy('status')
            ->pluck('count', 'status');

        $totalRevenue = (clone $query)
            ->where('status', 'completed')
            ->sum('total_cost');

        return [
            'total_jobs' => $statusCounts->sum(),
            'pending_jobs' => $statusCounts->get('pending', 0),
            'assigned_jobs' => $statusCounts->get('assigned', 0),
            'in_progress_jobs' => $statusCounts->get('in_progress', 0),
            'completed_jobs' => $statusCounts->get('completed', 0),
            'cancelled_jobs' => $statusCounts->get('cancelled', 0),
            'total_revenue' => round((float) $totalRevenue, 2),
        ];
    }

    public function getJobsByStatus(?string $from = null, ?string $to = null): array
    {
        $query = ServiceJob::query();
        $this->applyDateRange($query, $from, $to);

        return $query
            ->select('status', DB::raw('count(*) as count'), DB::raw('COALESCE(SUM(total_cost), 0) as revenue'))
            ->groupBy('status')
            ->orderByRaw("FIELD(status, 'pending','assigned','on_the_way','in_progress','completed','cancelled')")
            ->get()
            ->map(fn ($row) => [
                'status' => $row->status,
                'count' => (int) $row->count,
                'revenue' => round((float) $row->revenue, 2),
            ])
            ->all();
    }

    public function getJobsByDate(string $from, string $to): array
    {
        return ServiceJob::select(
            DB::raw('DATE(scheduled_date) as date'),
            DB::raw('count(*) as total'),
            DB::raw("SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed"),
            DB::raw("SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled"),
        )
            ->whereBetween('scheduled_date', [$from, $to])
            ->groupBy(DB::raw('DATE(scheduled_date)'))
            ->orderBy('date')
            ->get()
            ->map(fn ($row) => [
                'date' => $row->date,
                'total' => (int) $row->total,
                'completed' => (int) $row->completed,
                'cancelled' => (int) $row->cancelled,
            ])
            ->all();
    }

    public function getTechnicianPerformance(?string $from = null, ?string $to = null): array
    {
        $query = ServiceJob::select(
            'technician_id',
            DB::raw('count(*) as completed_jobs'),
            DB::raw('COALESCE(SUM(total_cost), 0) as total_revenue'),
            DB::raw('AVG(TIMESTAMPDIFF(MINUTE, started_at, completed_at)) as avg_duration_minutes'),
        )
            ->where('status', 'completed')
            ->whereNotNull('technician_id');

        $this->applyDateRange($query, $from, $to);

        return $query
            ->groupBy('technician_id')
            ->with('technician:id,name,email,phone')
            ->orderByDesc('completed_jobs')
            ->get()
            ->map(fn ($row) => [
                'technician' => [
                    'id' => $row->technician->id,
                    'name' => $row->technician->name,
                    'email' => $row->technician->email,
                    'phone' => $row->technician->phone,
                ],
                'completed_jobs' => (int) $row->completed_jobs,
                'total_revenue' => round((float) $row->total_revenue, 2),
                'avg_duration_minutes' => $row->avg_duration_minutes
                    ? round((float) $row->avg_duration_minutes)
                    : null,
            ])
            ->all();
    }

    private function applyDateRange($query, ?string $from, ?string $to): void
    {
        // Default to last 90 days when no date range provided to prevent full table scans
        if (! $from && ! $to) {
            $query->where('scheduled_date', '>=', now()->subDays(90)->toDateString());
            return;
        }

        if ($from) {
            $query->where('scheduled_date', '>=', $from);
        }
        if ($to) {
            $query->where('scheduled_date', '<=', $to);
        }
    }
}
