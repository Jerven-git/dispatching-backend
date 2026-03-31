<?php

namespace App\Console\Commands;

use App\Models\ServiceJob;
use Illuminate\Console\Command;

class GenerateRecurringJobs extends Command
{
    protected $signature = 'jobs:generate-recurring {--days=7 : How many days ahead to generate}';

    protected $description = 'Generate upcoming jobs from recurring job templates';

    public function handle(): int
    {
        $daysAhead = (int) $this->option('days');
        $endDate = now()->addDays($daysAhead)->toDateString();

        $recurringJobs = ServiceJob::where('recurring_frequency', '!=', 'none')
            ->where(function ($q) {
                $q->whereNull('recurring_end_date')
                    ->orWhere('recurring_end_date', '>=', now()->toDateString());
            })
            ->whereNull('parent_job_id') // Only templates, not generated children
            ->whereNotIn('status', ['cancelled'])
            ->get();

        $created = 0;

        foreach ($recurringJobs as $template) {
            $nextDate = $this->getNextDate($template->scheduled_date, $template->recurring_frequency);

            while ($nextDate <= $endDate) {
                // Skip if past recurring_end_date
                if ($template->recurring_end_date && $nextDate > $template->recurring_end_date->toDateString()) {
                    break;
                }

                // Skip if already generated for this date
                $exists = ServiceJob::where('parent_job_id', $template->id)
                    ->where('scheduled_date', $nextDate)
                    ->exists();

                if (! $exists) {
                    ServiceJob::create([
                        'customer_id' => $template->customer_id,
                        'service_id' => $template->service_id,
                        'technician_id' => $template->technician_id,
                        'created_by' => $template->created_by,
                        'status' => $template->technician_id ? 'assigned' : 'pending',
                        'priority' => $template->priority,
                        'description' => $template->description,
                        'address' => $template->address,
                        'scheduled_date' => $nextDate,
                        'scheduled_time' => $template->scheduled_time,
                        'total_cost' => $template->total_cost,
                        'recurring_frequency' => 'none',
                        'parent_job_id' => $template->id,
                    ]);
                    $created++;
                }

                $nextDate = $this->getNextDate($nextDate, $template->recurring_frequency);
            }
        }

        $this->info("Generated {$created} recurring job(s).");

        return self::SUCCESS;
    }

    private function getNextDate(string $fromDate, string $frequency): string
    {
        $date = \Carbon\Carbon::parse($fromDate);

        return match ($frequency) {
            'daily' => $date->addDay()->toDateString(),
            'weekly' => $date->addWeek()->toDateString(),
            'biweekly' => $date->addWeeks(2)->toDateString(),
            'monthly' => $date->addMonth()->toDateString(),
            default => $date->addYear()->toDateString(), // effectively never
        };
    }
}
