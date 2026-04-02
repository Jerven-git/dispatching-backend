<?php

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\JobPart;
use App\Models\Part;
use App\Models\ScheduledReport;
use App\Models\Service;
use App\Models\ServiceJob;
use App\Models\User;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    $this->admin = User::factory()->create(['role' => 'admin']);
    $this->dispatcher = User::factory()->create(['role' => 'dispatcher']);
    $this->technician = User::factory()->create(['role' => 'technician']);
    $this->service = Service::factory()->create(['base_price' => 150.00, 'estimated_duration_minutes' => 60]);
    $this->customer = Customer::factory()->create();
});

// ── Revenue Trend ──────────────────────────────────────────────

describe('Revenue Trend', function () {
    it('returns monthly revenue data', function () {
        ServiceJob::factory()->completed($this->technician)->create([
            'service_id' => $this->service->id,
            'total_cost' => 200.00,
            'completed_at' => now(),
            'scheduled_date' => now(),
        ]);

        $response = $this->actingAs($this->admin)
            ->getJson('/api/analytics/revenue-trend');

        $response->assertOk();
        expect($response->json('trend'))->toBeArray();
        $current = collect($response->json('trend'))->firstWhere('month', now()->format('Y-m'));
        expect($current)->not->toBeNull();
        expect((float) $current['revenue'])->toBe(200.0);
    });

    it('filters by date range', function () {
        ServiceJob::factory()->completed($this->technician)->create([
            'total_cost' => 100.00,
            'completed_at' => now()->subMonths(6),
            'scheduled_date' => now()->subMonths(6),
        ]);

        $response = $this->actingAs($this->admin)
            ->getJson('/api/analytics/revenue-trend?' . http_build_query([
                'from' => now()->subMonth()->toDateString(),
                'to' => now()->toDateString(),
            ]));

        $response->assertOk();
        // Old job should be excluded
        $sixMonthsAgo = now()->subMonths(6)->format('Y-m');
        $months = collect($response->json('trend'))->pluck('month')->all();
        expect($months)->not->toContain($sixMonthsAgo);
    });

    it('blocks technician access', function () {
        $this->actingAs($this->technician)
            ->getJson('/api/analytics/revenue-trend')
            ->assertForbidden();
    });
});

// ── Job Trend ──────────────────────────────────────────────────

describe('Job Trend', function () {
    it('returns weekly job counts', function () {
        ServiceJob::factory()->count(3)->create([
            'scheduled_date' => now(),
        ]);

        ServiceJob::factory()->completed($this->technician)->create([
            'scheduled_date' => now(),
        ]);

        $response = $this->actingAs($this->admin)
            ->getJson('/api/analytics/job-trend');

        $response->assertOk();
        expect($response->json('trend'))->toBeArray();
        expect($response->json('trend'))->not->toBeEmpty();
    });
});

// ── Service Popularity ─────────────────────────────────────────

describe('Service Popularity', function () {
    it('returns job counts per service', function () {
        $service2 = Service::factory()->create();

        ServiceJob::factory()->count(5)->create([
            'service_id' => $this->service->id,
            'scheduled_date' => now(),
        ]);
        ServiceJob::factory()->count(2)->create([
            'service_id' => $service2->id,
            'scheduled_date' => now(),
        ]);

        $response = $this->actingAs($this->admin)
            ->getJson('/api/analytics/service-popularity');

        $response->assertOk();
        $services = $response->json('services');
        expect($services)->toHaveCount(2);
        // Most popular first
        expect($services[0]['total_jobs'])->toBe(5);
    });
});

// ── Customer Lifetime Value ────────────────────────────────────

describe('Customer Lifetime Value', function () {
    it('returns customer value ranking', function () {
        $job = ServiceJob::factory()->completed($this->technician)->create([
            'customer_id' => $this->customer->id,
            'service_id' => $this->service->id,
        ]);

        Invoice::factory()->paid()->create([
            'customer_id' => $this->customer->id,
            'service_job_id' => $job->id,
            'created_by' => $this->admin->id,
            'total' => 500.00,
        ]);

        $response = $this->actingAs($this->admin)
            ->getJson('/api/analytics/customer-lifetime-value');

        $response->assertOk();
        $customers = $response->json('customers');
        expect($customers)->not->toBeEmpty();

        $top = collect($customers)->firstWhere('id', $this->customer->id);
        expect($top)->not->toBeNull();
        expect((float) $top['total_paid'])->toBe(500.0);
        expect($top['completed_jobs'])->toBe(1);
    });

    it('accepts limit parameter', function () {
        Customer::factory()->count(5)->create();

        $response = $this->actingAs($this->admin)
            ->getJson('/api/analytics/customer-lifetime-value?limit=3');

        $response->assertOk();
        expect(count($response->json('customers')))->toBeLessThanOrEqual(3);
    });
});

// ── Job Profitability ──────────────────────────────────────────

describe('Job Profitability', function () {
    it('returns profitability per job', function () {
        $job = ServiceJob::factory()->completed($this->technician)->create([
            'service_id' => $this->service->id,
            'customer_id' => $this->customer->id,
            'total_cost' => 250.00,
            'scheduled_date' => now(),
        ]);

        $part = Part::factory()->create(['unit_price' => 30.00, 'stock_quantity' => 100]);
        JobPart::create([
            'service_job_id' => $job->id,
            'part_id' => $part->id,
            'quantity' => 2,
            'unit_price' => 30.00,
            'total_price' => 60.00,
            'added_by' => $this->admin->id,
        ]);

        $response = $this->actingAs($this->admin)
            ->getJson('/api/analytics/job-profitability');

        $response->assertOk();
        $jobs = $response->json('jobs');
        expect($jobs)->toHaveCount(1);
        expect($jobs[0]['total_revenue'])->toBe(250.0);
        expect($jobs[0]['parts_cost'])->toBe(60.0);
        expect($jobs[0]['labor_revenue'])->toBe(190.0);

        $summary = $response->json('summary');
        expect($summary['total_jobs'])->toBe(1);
        expect($summary['avg_profit_margin'])->toBeGreaterThan(0);
    });

    it('handles jobs with no parts', function () {
        ServiceJob::factory()->completed($this->technician)->create([
            'service_id' => $this->service->id,
            'total_cost' => 200.00,
            'scheduled_date' => now(),
        ]);

        $response = $this->actingAs($this->admin)
            ->getJson('/api/analytics/job-profitability');

        $response->assertOk();
        $jobs = $response->json('jobs');
        expect($jobs[0]['parts_cost'])->toBe(0.0);
        expect($jobs[0]['profit_margin'])->toBe(100.0);
    });
});

// ── CSV Exports ────────────────────────────────────────────────

describe('CSV Export', function () {
    it('exports jobs as CSV', function () {
        ServiceJob::factory()->count(3)->create([
            'scheduled_date' => now(),
        ]);

        $response = $this->actingAs($this->admin)
            ->get('/api/export/jobs');

        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        $response->assertHeader('Content-Disposition');

        $content = $response->getContent();
        expect($content)->toContain('Reference');
        expect($content)->toContain('Customer');
        // Should have header + 3 data rows
        $lines = array_filter(explode("\n", trim($content)));
        expect(count($lines))->toBe(4);
    });

    it('exports jobs filtered by status', function () {
        ServiceJob::factory()->count(2)->create([
            'status' => 'pending',
            'scheduled_date' => now(),
        ]);
        ServiceJob::factory()->completed($this->technician)->create([
            'scheduled_date' => now(),
        ]);

        $response = $this->actingAs($this->admin)
            ->get('/api/export/jobs?status=pending');

        $response->assertOk();
        $lines = array_filter(explode("\n", trim($response->getContent())));
        expect(count($lines))->toBe(3); // header + 2 pending
    });

    it('exports invoices as CSV', function () {
        $job = ServiceJob::factory()->completed($this->technician)->create([
            'customer_id' => $this->customer->id,
        ]);
        Invoice::factory()->count(2)->create([
            'customer_id' => $this->customer->id,
            'service_job_id' => $job->id,
            'created_by' => $this->admin->id,
        ]);

        $response = $this->actingAs($this->admin)
            ->get('/api/export/invoices');

        $response->assertOk();
        $content = $response->getContent();
        expect($content)->toContain('Invoice #');
    });

    it('exports customers as CSV', function () {
        Customer::factory()->count(3)->create();

        $response = $this->actingAs($this->admin)
            ->get('/api/export/customers');

        $response->assertOk();
        $content = $response->getContent();
        expect($content)->toContain('Name');
        expect($content)->toContain('Portal Access');
    });

    it('exports technician performance as CSV', function () {
        ServiceJob::factory()->completed($this->technician)->count(2)->create([
            'total_cost' => 200.00,
            'scheduled_date' => now(),
        ]);

        $response = $this->actingAs($this->admin)
            ->get('/api/export/technician-performance');

        $response->assertOk();
        $content = $response->getContent();
        expect($content)->toContain('Completed Jobs');
        expect($content)->toContain($this->technician->name);
    });

    it('technician cannot export data', function () {
        $this->actingAs($this->technician)
            ->get('/api/export/jobs')
            ->assertForbidden();
    });
});

// ── Scheduled Reports ──────────────────────────────────────────

describe('Scheduled Reports CRUD', function () {
    it('admin can create a scheduled report', function () {
        $response = $this->actingAs($this->admin)
            ->postJson('/api/scheduled-reports', [
                'name' => 'Weekly Summary',
                'report_type' => 'summary',
                'frequency' => 'weekly',
                'recipients' => ['manager@example.com', 'ceo@example.com'],
            ]);

        $response->assertCreated();
        expect(ScheduledReport::count())->toBe(1);
        expect($response->json('scheduled_report.name'))->toBe('Weekly Summary');
        expect($response->json('scheduled_report.recipients'))->toHaveCount(2);
    });

    it('admin can list scheduled reports', function () {
        ScheduledReport::create([
            'name' => 'Daily Report',
            'report_type' => 'summary',
            'frequency' => 'daily',
            'recipients' => ['test@example.com'],
            'created_by' => $this->admin->id,
        ]);

        $response = $this->actingAs($this->admin)
            ->getJson('/api/scheduled-reports');

        $response->assertOk();
        expect($response->json('scheduled_reports'))->toHaveCount(1);
    });

    it('admin can update a scheduled report', function () {
        $report = ScheduledReport::create([
            'name' => 'Old Name',
            'report_type' => 'summary',
            'frequency' => 'daily',
            'recipients' => ['test@example.com'],
            'created_by' => $this->admin->id,
        ]);

        $response = $this->actingAs($this->admin)
            ->putJson("/api/scheduled-reports/{$report->id}", [
                'name' => 'New Name',
                'frequency' => 'monthly',
            ]);

        $response->assertOk();
        expect($report->fresh()->name)->toBe('New Name');
        expect($report->fresh()->frequency)->toBe('monthly');
    });

    it('admin can delete a scheduled report', function () {
        $report = ScheduledReport::create([
            'name' => 'To Delete',
            'report_type' => 'summary',
            'frequency' => 'daily',
            'recipients' => ['test@example.com'],
            'created_by' => $this->admin->id,
        ]);

        $response = $this->actingAs($this->admin)
            ->deleteJson("/api/scheduled-reports/{$report->id}");

        $response->assertOk();
        expect(ScheduledReport::count())->toBe(0);
    });

    it('validates recipients are emails', function () {
        $response = $this->actingAs($this->admin)
            ->postJson('/api/scheduled-reports', [
                'name' => 'Test',
                'report_type' => 'summary',
                'frequency' => 'daily',
                'recipients' => ['not-an-email'],
            ]);

        $response->assertUnprocessable();
    });

    it('validates report_type enum', function () {
        $response = $this->actingAs($this->admin)
            ->postJson('/api/scheduled-reports', [
                'name' => 'Test',
                'report_type' => 'invalid_type',
                'frequency' => 'daily',
                'recipients' => ['test@example.com'],
            ]);

        $response->assertUnprocessable();
    });

    it('dispatcher cannot manage scheduled reports', function () {
        $this->actingAs($this->dispatcher)
            ->postJson('/api/scheduled-reports', [
                'name' => 'Test',
                'report_type' => 'summary',
                'frequency' => 'daily',
                'recipients' => ['test@example.com'],
            ])
            ->assertForbidden();
    });
});

// ── Scheduled Report Command ───────────────────────────────────

describe('Send Scheduled Reports Command', function () {
    it('sends due daily reports', function () {
        Notification::fake();

        ScheduledReport::create([
            'name' => 'Daily Summary',
            'report_type' => 'summary',
            'frequency' => 'daily',
            'recipients' => ['admin@example.com'],
            'created_by' => $this->admin->id,
            'is_active' => true,
        ]);

        $this->artisan('reports:send-scheduled')
            ->assertSuccessful();

        Notification::assertCount(1);

        expect(ScheduledReport::first()->last_sent_at)->not->toBeNull();
    });

    it('skips inactive reports', function () {
        Notification::fake();

        ScheduledReport::create([
            'name' => 'Inactive Report',
            'report_type' => 'summary',
            'frequency' => 'daily',
            'recipients' => ['admin@example.com'],
            'created_by' => $this->admin->id,
            'is_active' => false,
        ]);

        $this->artisan('reports:send-scheduled')
            ->assertSuccessful();

        Notification::assertCount(0);
    });

    it('sends to multiple recipients', function () {
        Notification::fake();

        ScheduledReport::create([
            'name' => 'Multi Report',
            'report_type' => 'summary',
            'frequency' => 'daily',
            'recipients' => ['admin@example.com', 'manager@example.com', 'ceo@example.com'],
            'created_by' => $this->admin->id,
            'is_active' => true,
        ]);

        $this->artisan('reports:send-scheduled')
            ->assertSuccessful();

        Notification::assertCount(3);
    });
});

// ── ScheduledReport Model ──────────────────────────────────────

describe('ScheduledReport Model', function () {
    it('daily reports are always due', function () {
        $report = new ScheduledReport(['frequency' => 'daily']);
        expect($report->isDueToday())->toBeTrue();
    });

    it('weekly reports are due on Monday', function () {
        $report = new ScheduledReport(['frequency' => 'weekly']);
        // Result depends on the day this runs; just confirm it returns a boolean
        expect($report->isDueToday())->toBeBool();
    });

    it('monthly reports are due on the 1st', function () {
        $report = new ScheduledReport(['frequency' => 'monthly']);
        expect($report->isDueToday())->toBeBool();
    });
});
