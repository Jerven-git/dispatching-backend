<?php

use App\Models\ServiceJob;
use App\Models\User;

beforeEach(function () {
    $this->admin = User::factory()->create(['role' => 'admin']);
    $this->technician = User::factory()->create(['role' => 'technician']);
});

describe('Report Summary', function () {
    it('returns job counts and revenue', function () {
        ServiceJob::factory()->count(3)->create([
            'status' => 'pending',
            'scheduled_date' => now(),
        ]);
        ServiceJob::factory()->completed($this->technician)->count(2)->create([
            'total_cost' => 150.00,
            'scheduled_date' => now(),
        ]);

        $response = $this->actingAs($this->admin)
            ->getJson('/api/reports/summary');

        $response->assertOk();
        expect($response->json('summary.total_jobs'))->toBe(5);
        expect($response->json('summary.pending_jobs'))->toBe(3);
        expect($response->json('summary.completed_jobs'))->toBe(2);
        expect((float) $response->json('summary.total_revenue'))->toBe(300.0);
    });

    it('filters by date range', function () {
        ServiceJob::factory()->create([
            'status' => 'pending',
            'scheduled_date' => now()->subDays(100),
        ]);
        ServiceJob::factory()->create([
            'status' => 'pending',
            'scheduled_date' => now(),
        ]);

        $response = $this->actingAs($this->admin)
            ->getJson('/api/reports/summary?' . http_build_query([
                'from' => now()->toDateString(),
                'to' => now()->toDateString(),
            ]));

        $response->assertOk();
        expect($response->json('summary.total_jobs'))->toBe(1);
    });

    it('blocks technician access to reports', function () {
        $response = $this->actingAs($this->technician)
            ->getJson('/api/reports/summary');

        $response->assertForbidden();
    });
});

describe('Jobs by Status', function () {
    it('returns jobs grouped by status', function () {
        ServiceJob::factory()->count(2)->create([
            'status' => 'pending',
            'scheduled_date' => now(),
        ]);
        ServiceJob::factory()->completed($this->technician)->count(1)->create([
            'scheduled_date' => now(),
        ]);

        $response = $this->actingAs($this->admin)
            ->getJson('/api/reports/jobs-by-status');

        $response->assertOk();
        $data = collect($response->json('statuses'));
        expect($data->firstWhere('status', 'pending')['count'])->toBe(2);
        expect($data->firstWhere('status', 'completed')['count'])->toBe(1);
    });
});

describe('Jobs by Date', function () {
    it('validates from and to are required', function () {
        $response = $this->actingAs($this->admin)
            ->getJson('/api/reports/jobs-by-date');

        $response->assertUnprocessable();
    });

    it('returns daily breakdown', function () {
        $today = now()->toDateString();
        ServiceJob::factory()->count(3)->create(['scheduled_date' => $today]);
        ServiceJob::factory()->completed($this->technician)->create(['scheduled_date' => $today]);

        $response = $this->actingAs($this->admin)
            ->getJson('/api/reports/jobs-by-date?' . http_build_query([
                'from' => $today,
                'to' => $today,
            ]));

        $response->assertOk();
        $data = $response->json('dates');
        expect($data)->toHaveCount(1);
        expect($data[0]['date'])->toBe($today);
        expect($data[0]['total'])->toBe(4);
        expect($data[0]['completed'])->toBe(1);
    });
});

describe('Technician Performance', function () {
    it('returns performance metrics for technicians', function () {
        ServiceJob::factory()->completed($this->technician)->count(3)->create([
            'total_cost' => 200.00,
            'scheduled_date' => now(),
        ]);

        $response = $this->actingAs($this->admin)
            ->getJson('/api/reports/technician-performance');

        $response->assertOk();
        $data = $response->json('technicians');
        expect($data)->toHaveCount(1);
        expect($data[0]['technician']['id'])->toBe($this->technician->id);
        expect($data[0]['completed_jobs'])->toBe(3);
        expect((float) $data[0]['total_revenue'])->toBe(600.0);
    });
});
