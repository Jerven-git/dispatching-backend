<?php

use App\Models\ServiceJob;
use App\Models\User;

beforeEach(function () {
    $this->admin = User::factory()->create(['role' => 'admin']);
    $this->technician = User::factory()->create(['role' => 'technician']);
});

describe('Calendar API', function () {
    it('returns jobs for a date range', function () {
        ServiceJob::factory()->count(3)->create([
            'scheduled_date' => now()->toDateString(),
        ]);
        ServiceJob::factory()->create([
            'scheduled_date' => now()->addDays(60)->toDateString(),
        ]);

        $response = $this->actingAs($this->admin)
            ->getJson('/api/service-jobs/calendar?' . http_build_query([
                'from' => now()->toDateString(),
                'to' => now()->addDays(30)->toDateString(),
            ]));

        $response->assertOk();
        expect($response->json('jobs'))->toHaveCount(3);
    });

    it('excludes cancelled jobs from calendar', function () {
        ServiceJob::factory()->create([
            'scheduled_date' => now()->toDateString(),
            'status' => 'pending',
        ]);
        ServiceJob::factory()->create([
            'scheduled_date' => now()->toDateString(),
            'status' => 'cancelled',
            'cancelled_at' => now(),
        ]);

        $response = $this->actingAs($this->admin)
            ->getJson('/api/service-jobs/calendar?' . http_build_query([
                'from' => now()->toDateString(),
                'to' => now()->toDateString(),
            ]));

        $response->assertOk();
        expect($response->json('jobs'))->toHaveCount(1);
    });

    it('requires from and to parameters', function () {
        $response = $this->actingAs($this->admin)
            ->getJson('/api/service-jobs/calendar');

        $response->assertUnprocessable();
    });

    it('blocks technician access', function () {
        $response = $this->actingAs($this->technician)
            ->getJson('/api/service-jobs/calendar?' . http_build_query([
                'from' => now()->toDateString(),
                'to' => now()->addDays(30)->toDateString(),
            ]));

        $response->assertForbidden();
    });
});

describe('Scheduling Conflict Detection', function () {
    it('warns when technician has a conflict', function () {
        $existingJob = ServiceJob::factory()->assigned($this->technician)->create([
            'scheduled_date' => now()->addDay()->toDateString(),
        ]);

        $response = $this->actingAs($this->admin)
            ->postJson('/api/service-jobs', [
                'customer_id' => $existingJob->customer_id,
                'service_id' => $existingJob->service_id,
                'technician_id' => $this->technician->id,
                'address' => '123 Test St',
                'scheduled_date' => now()->addDay()->toDateString(),
            ]);

        $response->assertUnprocessable();
        expect($response->json('errors.technician_id'))->not->toBeEmpty();
    });

    it('allows override with force flag', function () {
        $existingJob = ServiceJob::factory()->assigned($this->technician)->create([
            'scheduled_date' => now()->addDay()->toDateString(),
        ]);

        $response = $this->actingAs($this->admin)
            ->postJson('/api/service-jobs', [
                'customer_id' => $existingJob->customer_id,
                'service_id' => $existingJob->service_id,
                'technician_id' => $this->technician->id,
                'address' => '123 Test St',
                'scheduled_date' => now()->addDay()->toDateString(),
                'force' => true,
            ]);

        $response->assertCreated();
    });

    it('no warning when no conflict', function () {
        $existingJob = ServiceJob::factory()->create();

        $response = $this->actingAs($this->admin)
            ->postJson('/api/service-jobs', [
                'customer_id' => $existingJob->customer_id,
                'service_id' => $existingJob->service_id,
                'technician_id' => $this->technician->id,
                'address' => '456 Test Ave',
                'scheduled_date' => now()->addDays(5)->toDateString(),
            ]);

        $response->assertCreated();
    });
});

describe('Recurring Jobs', function () {
    it('can create a recurring job template', function () {
        $job = ServiceJob::factory()->create([
            'recurring_frequency' => 'weekly',
            'recurring_end_date' => now()->addMonths(3)->toDateString(),
        ]);

        expect($job->isRecurring())->toBeTrue();
        expect($job->recurring_frequency)->toBe('weekly');
    });

    it('generates recurring jobs via artisan command', function () {
        $template = ServiceJob::factory()->create([
            'scheduled_date' => now()->toDateString(),
            'recurring_frequency' => 'weekly',
            'recurring_end_date' => now()->addMonths(1)->toDateString(),
        ]);

        $this->artisan('jobs:generate-recurring', ['--days' => 30])
            ->expectsOutputToContain('recurring job')
            ->assertSuccessful();

        $childJobs = ServiceJob::where('parent_job_id', $template->id)->count();
        expect($childJobs)->toBeGreaterThanOrEqual(3); // ~4 weeks
    });

    it('does not duplicate recurring jobs', function () {
        $template = ServiceJob::factory()->create([
            'scheduled_date' => now()->toDateString(),
            'recurring_frequency' => 'weekly',
            'recurring_end_date' => now()->addWeeks(2)->toDateString(),
        ]);

        $this->artisan('jobs:generate-recurring', ['--days' => 30])->assertSuccessful();
        $firstCount = ServiceJob::where('parent_job_id', $template->id)->count();

        $this->artisan('jobs:generate-recurring', ['--days' => 30])->assertSuccessful();
        $secondCount = ServiceJob::where('parent_job_id', $template->id)->count();

        expect($secondCount)->toBe($firstCount);
    });

    it('respects recurring_end_date', function () {
        $template = ServiceJob::factory()->create([
            'scheduled_date' => now()->toDateString(),
            'recurring_frequency' => 'daily',
            'recurring_end_date' => now()->addDays(3)->toDateString(),
        ]);

        $this->artisan('jobs:generate-recurring', ['--days' => 30])->assertSuccessful();

        $childJobs = ServiceJob::where('parent_job_id', $template->id)->count();
        expect($childJobs)->toBe(3); // 3 days after today
    });
});
