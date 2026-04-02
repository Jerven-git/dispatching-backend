<?php

namespace App\Console\Commands;

use App\Models\ScheduledReport;
use App\Notifications\ScheduledReportMail;
use App\Services\AnalyticsService;
use App\Services\ReportService;
use Illuminate\Console\Command;
use Illuminate\Notifications\AnonymousNotifiable;

class SendScheduledReports extends Command
{
    protected $signature = 'reports:send-scheduled';

    protected $description = 'Send all due scheduled report emails';

    public function handle(ReportService $reportService, AnalyticsService $analyticsService): int
    {
        $reports = ScheduledReport::where('is_active', true)->get();

        $sent = 0;

        foreach ($reports as $report) {
            if (! $report->isDueToday()) {
                continue;
            }

            $data = $this->generateReportData($report, $reportService, $analyticsService);

            if ($data === null) {
                $this->warn("Unknown report type: {$report->report_type}");
                continue;
            }

            foreach ($report->recipients as $email) {
                (new AnonymousNotifiable)
                    ->route('mail', $email)
                    ->notify(new ScheduledReportMail(
                        $report->name,
                        $report->report_type,
                        $data,
                    ));
            }

            $report->update(['last_sent_at' => now()]);
            $sent++;

            $this->info("Sent: {$report->name} to " . implode(', ', $report->recipients));
        }

        $this->info("Done. {$sent} report(s) sent.");

        return self::SUCCESS;
    }

    private function generateReportData(
        ScheduledReport $report,
        ReportService $reportService,
        AnalyticsService $analyticsService,
    ): ?array {
        $params = $report->parameters ?? [];
        $from = $params['from'] ?? null;
        $to = $params['to'] ?? null;

        return match ($report->report_type) {
            'summary' => $reportService->getSummary($from, $to),
            'jobs_by_status' => ['statuses' => $reportService->getJobsByStatus($from, $to)],
            'jobs_by_date' => ['dates' => $reportService->getJobsByDate(
                $from ?? now()->subDays(30)->toDateString(),
                $to ?? now()->toDateString(),
            )],
            'technician_performance' => ['technicians' => $reportService->getTechnicianPerformance($from, $to)],
            'customer_lifetime_value' => ['customers' => $analyticsService->getCustomerLifetimeValue()],
            'job_profitability' => $analyticsService->getProfitabilitySummary($from, $to),
            default => null,
        };
    }
}
