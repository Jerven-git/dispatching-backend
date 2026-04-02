<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\JobPart;
use App\Models\ServiceJob;
use Illuminate\Support\Facades\DB;

class AnalyticsService
{
    /**
     * Revenue trend — monthly revenue for the given period.
     */
    public function getRevenueTrend(?string $from = null, ?string $to = null): array
    {
        $from = $from ?? now()->subMonths(12)->startOfMonth()->toDateString();
        $to = $to ?? now()->toDateString();

        return ServiceJob::select(
            DB::raw("DATE_FORMAT(completed_at, '%Y-%m') as month"),
            DB::raw('COUNT(*) as jobs_completed'),
            DB::raw('COALESCE(SUM(total_cost), 0) as revenue'),
        )
            ->where('status', 'completed')
            ->whereBetween('completed_at', [$from, $to])
            ->groupBy('month')
            ->orderBy('month')
            ->get()
            ->map(fn ($row) => [
                'month' => $row->month,
                'jobs_completed' => (int) $row->jobs_completed,
                'revenue' => round((float) $row->revenue, 2),
            ])
            ->all();
    }

    /**
     * Job trend — weekly job counts by status for chart visualization.
     */
    public function getJobTrend(?string $from = null, ?string $to = null): array
    {
        $from = $from ?? now()->subWeeks(12)->startOfWeek()->toDateString();
        $to = $to ?? now()->toDateString();

        return ServiceJob::select(
            DB::raw("DATE_FORMAT(scheduled_date, '%x-W%v') as week"),
            DB::raw('MIN(scheduled_date) as week_start'),
            DB::raw('COUNT(*) as total'),
            DB::raw("SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed"),
            DB::raw("SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled"),
            DB::raw("SUM(CASE WHEN status NOT IN ('completed','cancelled') THEN 1 ELSE 0 END) as active"),
        )
            ->whereBetween('scheduled_date', [$from, $to])
            ->groupBy('week')
            ->orderBy('week')
            ->get()
            ->map(fn ($row) => [
                'week' => $row->week,
                'week_start' => $row->week_start,
                'total' => (int) $row->total,
                'completed' => (int) $row->completed,
                'cancelled' => (int) $row->cancelled,
                'active' => (int) $row->active,
            ])
            ->all();
    }

    /**
     * Service popularity — jobs and revenue per service type.
     */
    public function getServicePopularity(?string $from = null, ?string $to = null): array
    {
        $query = ServiceJob::select(
            'service_id',
            DB::raw('COUNT(*) as total_jobs'),
            DB::raw("SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_jobs"),
            DB::raw('COALESCE(SUM(total_cost), 0) as revenue'),
        )
            ->groupBy('service_id')
            ->with('service:id,name,base_price')
            ->orderByDesc('total_jobs');

        $this->applyDateRange($query, $from, $to);

        return $query->get()
            ->map(fn ($row) => [
                'service' => [
                    'id' => $row->service->id,
                    'name' => $row->service->name,
                    'base_price' => $row->service->base_price,
                ],
                'total_jobs' => (int) $row->total_jobs,
                'completed_jobs' => (int) $row->completed_jobs,
                'revenue' => round((float) $row->revenue, 2),
            ])
            ->all();
    }

    /**
     * Customer lifetime value — total spend, job count, avg job value per customer.
     */
    public function getCustomerLifetimeValue(int $limit = 20): array
    {
        return Customer::select(
            'customers.id',
            'customers.name',
            'customers.email',
            'customers.created_at as customer_since',
            DB::raw('COUNT(DISTINCT sj.id) as total_jobs'),
            DB::raw("SUM(CASE WHEN sj.status = 'completed' THEN 1 ELSE 0 END) as completed_jobs"),
            DB::raw('COALESCE(SUM(i.total), 0) as total_spent'),
            DB::raw("SUM(CASE WHEN i.status = 'paid' THEN i.total ELSE 0 END) as total_paid"),
        )
            ->leftJoin('service_jobs as sj', 'customers.id', '=', 'sj.customer_id')
            ->leftJoin('invoices as i', 'customers.id', '=', 'i.customer_id')
            ->whereNull('customers.deleted_at')
            ->groupBy('customers.id', 'customers.name', 'customers.email', 'customers.created_at')
            ->orderByDesc('total_spent')
            ->limit($limit)
            ->get()
            ->map(fn ($row) => [
                'id' => $row->id,
                'name' => $row->name,
                'email' => $row->email,
                'customer_since' => $row->customer_since,
                'total_jobs' => (int) $row->total_jobs,
                'completed_jobs' => (int) $row->completed_jobs,
                'total_spent' => round((float) $row->total_spent, 2),
                'total_paid' => round((float) $row->total_paid, 2),
                'avg_job_value' => $row->completed_jobs > 0
                    ? round((float) $row->total_spent / $row->completed_jobs, 2)
                    : 0,
            ])
            ->all();
    }

    /**
     * Job profitability — compare revenue vs parts cost per job.
     */
    public function getJobProfitability(?string $from = null, ?string $to = null, int $limit = 50): array
    {
        $query = ServiceJob::select(
            'service_jobs.id',
            'service_jobs.reference_number',
            'service_jobs.total_cost',
            'service_jobs.scheduled_date',
            'service_jobs.customer_id',
            'service_jobs.service_id',
            DB::raw('COALESCE(SUM(jp.total_price), 0) as parts_cost'),
        )
            ->leftJoin('job_parts as jp', 'service_jobs.id', '=', 'jp.service_job_id')
            ->where('service_jobs.status', 'completed')
            ->groupBy(
                'service_jobs.id',
                'service_jobs.reference_number',
                'service_jobs.total_cost',
                'service_jobs.scheduled_date',
                'service_jobs.customer_id',
                'service_jobs.service_id',
            )
            ->with(['customer:id,name', 'service:id,name,base_price'])
            ->orderByDesc('service_jobs.scheduled_date')
            ->limit($limit);

        if ($from) {
            $query->where('service_jobs.scheduled_date', '>=', $from);
        }
        if ($to) {
            $query->where('service_jobs.scheduled_date', '<=', $to);
        }

        return $query->get()
            ->map(fn ($row) => [
                'id' => $row->id,
                'reference_number' => $row->reference_number,
                'customer' => $row->customer?->name,
                'service' => $row->service?->name,
                'scheduled_date' => $row->scheduled_date,
                'total_revenue' => round((float) $row->total_cost, 2),
                'parts_cost' => round((float) $row->parts_cost, 2),
                'labor_revenue' => round((float) $row->total_cost - (float) $row->parts_cost, 2),
                'profit_margin' => $row->total_cost > 0
                    ? round(((float) $row->total_cost - (float) $row->parts_cost) / (float) $row->total_cost * 100, 1)
                    : 0,
            ])
            ->all();
    }

    /**
     * Profitability summary — aggregated averages.
     */
    public function getProfitabilitySummary(?string $from = null, ?string $to = null): array
    {
        $jobs = $this->getJobProfitability($from, $to, 1000);

        if (empty($jobs)) {
            return [
                'total_jobs' => 0,
                'total_revenue' => 0,
                'total_parts_cost' => 0,
                'total_labor_revenue' => 0,
                'avg_profit_margin' => 0,
            ];
        }

        $totalRevenue = array_sum(array_column($jobs, 'total_revenue'));
        $totalPartsCost = array_sum(array_column($jobs, 'parts_cost'));

        return [
            'total_jobs' => count($jobs),
            'total_revenue' => round($totalRevenue, 2),
            'total_parts_cost' => round($totalPartsCost, 2),
            'total_labor_revenue' => round($totalRevenue - $totalPartsCost, 2),
            'avg_profit_margin' => $totalRevenue > 0
                ? round(($totalRevenue - $totalPartsCost) / $totalRevenue * 100, 1)
                : 0,
        ];
    }

    private function applyDateRange($query, ?string $from, ?string $to): void
    {
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
