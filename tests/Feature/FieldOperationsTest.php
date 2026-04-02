<?php

use App\Models\ChecklistItem;
use App\Models\Customer;
use App\Models\JobChecklistEntry;
use App\Models\Service;
use App\Models\ServiceJob;
use App\Models\TechnicianLocation;
use App\Models\User;
use App\Services\LocationTrackingService;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    $this->admin = User::factory()->create(['role' => 'admin']);
    $this->dispatcher = User::factory()->create(['role' => 'dispatcher']);
    $this->technician = User::factory()->create(['role' => 'technician']);
    $this->customer = Customer::factory()->create([
        'password' => Hash::make('customer123'),
        'portal_access' => true,
    ]);
    $this->service = Service::factory()->create(['estimated_duration_minutes' => 60]);
});

// ── GPS Location Tracking ──────────────────────────────────────

describe('Location Tracking', function () {
    it('technician can push their location', function () {
        $response = $this->actingAs($this->technician)
            ->postJson('/api/my-location', [
                'latitude' => 40.7128,
                'longitude' => -74.0060,
                'accuracy' => 10.5,
                'heading' => 180.00,
                'speed' => 35.50,
            ]);

        $response->assertCreated();
        expect(TechnicianLocation::count())->toBe(1);
        expect(TechnicianLocation::first()->user_id)->toBe($this->technician->id);
    });

    it('validates latitude and longitude ranges', function () {
        $response = $this->actingAs($this->technician)
            ->postJson('/api/my-location', [
                'latitude' => 95.0, // invalid: > 90
                'longitude' => -74.0060,
            ]);

        $response->assertUnprocessable();
    });

    it('non-technician cannot push location', function () {
        $response = $this->actingAs($this->admin)
            ->postJson('/api/my-location', [
                'latitude' => 40.7128,
                'longitude' => -74.0060,
            ]);

        $response->assertForbidden();
    });

    it('admin can view all technician locations', function () {
        TechnicianLocation::factory()->create([
            'user_id' => $this->technician->id,
            'recorded_at' => now(),
        ]);

        $response = $this->actingAs($this->admin)
            ->getJson('/api/technicians/locations');

        $response->assertOk();
        expect($response->json('technicians'))->toBeArray();

        $tech = collect($response->json('technicians'))->firstWhere('id', $this->technician->id);
        expect($tech['location'])->not->toBeNull();
    });

    it('admin can view a specific technician location', function () {
        TechnicianLocation::factory()->create([
            'user_id' => $this->technician->id,
            'latitude' => 40.7128,
            'longitude' => -74.0060,
            'recorded_at' => now(),
        ]);

        $response = $this->actingAs($this->admin)
            ->getJson("/api/technicians/{$this->technician->id}/location");

        $response->assertOk();
        expect($response->json('location.latitude'))->toBe('40.7128000');
    });

    it('returns null when technician has no location data', function () {
        $response = $this->actingAs($this->admin)
            ->getJson("/api/technicians/{$this->technician->id}/location");

        $response->assertOk();
        expect($response->json('location'))->toBeNull();
    });

    it('returns latest location when multiple exist', function () {
        TechnicianLocation::factory()->create([
            'user_id' => $this->technician->id,
            'latitude' => 40.0000,
            'recorded_at' => now()->subMinutes(10),
        ]);

        TechnicianLocation::factory()->create([
            'user_id' => $this->technician->id,
            'latitude' => 41.0000,
            'recorded_at' => now(),
        ]);

        $response = $this->actingAs($this->admin)
            ->getJson("/api/technicians/{$this->technician->id}/location");

        $response->assertOk();
        expect($response->json('location.latitude'))->toBe('41.0000000');
    });
});

// ── Route Optimization ─────────────────────────────────────────

describe('Route Optimization', function () {
    it('admin can get optimized route for a technician', function () {
        $today = today()->toDateString();

        // Create 3 jobs at different coordinates
        ServiceJob::factory()->assigned($this->technician)->create([
            'service_id' => $this->service->id,
            'scheduled_date' => $today,
            'latitude' => 40.7128,
            'longitude' => -74.0060,
        ]);

        ServiceJob::factory()->assigned($this->technician)->create([
            'service_id' => $this->service->id,
            'scheduled_date' => $today,
            'latitude' => 40.7580,
            'longitude' => -73.9855,
        ]);

        ServiceJob::factory()->assigned($this->technician)->create([
            'service_id' => $this->service->id,
            'scheduled_date' => $today,
            'latitude' => 40.7484,
            'longitude' => -73.9857,
        ]);

        $response = $this->actingAs($this->admin)
            ->getJson("/api/technicians/{$this->technician->id}/route?date={$today}");

        $response->assertOk();
        expect($response->json('route.jobs'))->toHaveCount(3);
        expect($response->json('route.total_distance_km'))->toBeGreaterThan(0);
        expect($response->json('route.estimated_travel_minutes'))->toBeGreaterThanOrEqual(0);
    });

    it('technician can get their own route', function () {
        $today = today()->toDateString();

        ServiceJob::factory()->assigned($this->technician)->create([
            'service_id' => $this->service->id,
            'scheduled_date' => $today,
            'latitude' => 40.7128,
            'longitude' => -74.0060,
        ]);

        $response = $this->actingAs($this->technician)
            ->getJson('/api/my-route');

        $response->assertOk();
        expect($response->json('route.jobs'))->toHaveCount(1);
    });

    it('returns empty route when no jobs for the date', function () {
        $response = $this->actingAs($this->technician)
            ->getJson('/api/my-route?date=2030-01-01');

        $response->assertOk();
        expect($response->json('route.jobs'))->toHaveCount(0);
        expect($response->json('route.total_distance_km'))->toBe(0);
    });

    it('excludes completed and cancelled jobs from route', function () {
        $today = today()->toDateString();

        ServiceJob::factory()->assigned($this->technician)->create([
            'service_id' => $this->service->id,
            'scheduled_date' => $today,
            'latitude' => 40.7128,
            'longitude' => -74.0060,
        ]);

        ServiceJob::factory()->completed($this->technician)->create([
            'service_id' => $this->service->id,
            'scheduled_date' => $today,
            'latitude' => 40.7580,
            'longitude' => -73.9855,
        ]);

        $response = $this->actingAs($this->technician)
            ->getJson('/api/my-route');

        $response->assertOk();
        expect($response->json('route.jobs'))->toHaveCount(1);
    });

    it('uses technician current location as starting point when available', function () {
        $today = today()->toDateString();

        TechnicianLocation::factory()->create([
            'user_id' => $this->technician->id,
            'latitude' => 40.6892,
            'longitude' => -74.0445,
            'recorded_at' => now(),
        ]);

        ServiceJob::factory()->assigned($this->technician)->create([
            'service_id' => $this->service->id,
            'scheduled_date' => $today,
            'latitude' => 40.7128,
            'longitude' => -74.0060,
        ]);

        $response = $this->actingAs($this->admin)
            ->getJson("/api/technicians/{$this->technician->id}/route?date={$today}");

        $response->assertOk();
        expect($response->json('current_location'))->not->toBeNull();
    });
});

// ── ETA ────────────────────────────────────────────────────────

describe('ETA', function () {
    it('admin can get ETA for a job', function () {
        $job = ServiceJob::factory()->assigned($this->technician)->create([
            'service_id' => $this->service->id,
            'latitude' => 40.7580,
            'longitude' => -73.9855,
        ]);

        TechnicianLocation::factory()->create([
            'user_id' => $this->technician->id,
            'latitude' => 40.7128,
            'longitude' => -74.0060,
            'recorded_at' => now(),
        ]);

        $response = $this->actingAs($this->admin)
            ->getJson("/api/service-jobs/{$job->id}/eta");

        $response->assertOk();
        expect($response->json('eta.eta'))->not->toBeNull();
        expect($response->json('eta.distance_km'))->toBeGreaterThan(0);
        expect($response->json('eta.travel_minutes'))->toBeGreaterThan(0);
    });

    it('returns on_site status for in-progress jobs', function () {
        $job = ServiceJob::factory()->inProgress($this->technician)->create([
            'service_id' => $this->service->id,
            'latitude' => 40.7580,
            'longitude' => -73.9855,
        ]);

        $response = $this->actingAs($this->admin)
            ->getJson("/api/service-jobs/{$job->id}/eta");

        $response->assertOk();
        expect($response->json('eta.technician_status'))->toBe('on_site');
    });

    it('returns unassigned status when no technician', function () {
        $job = ServiceJob::factory()->create([
            'service_id' => $this->service->id,
            'latitude' => 40.7580,
            'longitude' => -73.9855,
        ]);

        $response = $this->actingAs($this->admin)
            ->getJson("/api/service-jobs/{$job->id}/eta");

        $response->assertOk();
        expect($response->json('eta.technician_status'))->toBe('unassigned');
    });

    it('returns message when technician has no location data', function () {
        $job = ServiceJob::factory()->assigned($this->technician)->create([
            'service_id' => $this->service->id,
            'latitude' => 40.7580,
            'longitude' => -73.9855,
        ]);

        $response = $this->actingAs($this->admin)
            ->getJson("/api/service-jobs/{$job->id}/eta");

        $response->assertOk();
        expect($response->json('eta.message'))->toBe('Technician location not available.');
    });

    it('technician can get ETA for their own job', function () {
        $job = ServiceJob::factory()->assigned($this->technician)->create([
            'service_id' => $this->service->id,
            'latitude' => 40.7580,
            'longitude' => -73.9855,
        ]);

        TechnicianLocation::factory()->create([
            'user_id' => $this->technician->id,
            'latitude' => 40.7128,
            'longitude' => -74.0060,
            'recorded_at' => now(),
        ]);

        $response = $this->actingAs($this->technician)
            ->getJson("/api/my-jobs/{$job->id}/eta");

        $response->assertOk();
        expect($response->json('eta.travel_minutes'))->toBeGreaterThan(0);
    });

    it('customer can get ETA via portal', function () {
        $job = ServiceJob::factory()->assigned($this->technician)->create([
            'customer_id' => $this->customer->id,
            'service_id' => $this->service->id,
            'latitude' => 40.7580,
            'longitude' => -73.9855,
        ]);

        TechnicianLocation::factory()->create([
            'user_id' => $this->technician->id,
            'latitude' => 40.7128,
            'longitude' => -74.0060,
            'recorded_at' => now(),
        ]);

        $response = $this->actingAs($this->customer, 'customer')
            ->getJson("/api/portal/jobs/{$job->id}/eta");

        $response->assertOk();
        expect($response->json('eta.travel_minutes'))->toBeGreaterThan(0);
    });

    it('customer cannot get ETA for another customers job', function () {
        $otherCustomer = Customer::factory()->create();
        $job = ServiceJob::factory()->assigned($this->technician)->create([
            'customer_id' => $otherCustomer->id,
        ]);

        $response = $this->actingAs($this->customer, 'customer')
            ->getJson("/api/portal/jobs/{$job->id}/eta");

        $response->assertNotFound();
    });
});

// ── Haversine Distance ─────────────────────────────────────────

describe('Haversine Distance', function () {
    it('calculates distance between two known points', function () {
        // New York City to Los Angeles: ~3,944 km
        $distance = LocationTrackingService::haversineDistance(
            40.7128, -74.0060, // NYC
            34.0522, -118.2437, // LA
        );

        expect($distance)->toBeGreaterThan(3900);
        expect($distance)->toBeLessThan(4000);
    });

    it('returns zero for same coordinates', function () {
        $distance = LocationTrackingService::haversineDistance(
            40.7128, -74.0060,
            40.7128, -74.0060,
        );

        expect($distance)->toBe(0.0);
    });

    it('calculates short distances accurately', function () {
        // Times Square to Empire State Building: ~1.1 km
        $distance = LocationTrackingService::haversineDistance(
            40.7580, -73.9855, // Times Square
            40.7484, -73.9857, // Empire State
        );

        expect($distance)->toBeGreaterThan(0.5);
        expect($distance)->toBeLessThan(2.0);
    });
});

// ── Offline Sync ───────────────────────────────────────────────

describe('Offline Sync', function () {
    it('technician can sync batch of status updates', function () {
        $job = ServiceJob::factory()->assigned($this->technician)->create();

        $response = $this->actingAs($this->technician)
            ->postJson('/api/sync', [
                'last_synced_at' => now()->subHour()->toIso8601String(),
                'actions' => [
                    [
                        'type' => 'status_update',
                        'job_id' => $job->id,
                        'status' => 'on_the_way',
                        'timestamp' => now()->subMinutes(30)->toIso8601String(),
                    ],
                ],
            ]);

        $response->assertOk();
        expect($response->json('processed'))->toBe(1);
        expect($response->json('errors'))->toBeEmpty();
        expect($job->fresh()->status)->toBe('on_the_way');
    });

    it('technician can sync location updates', function () {
        $response = $this->actingAs($this->technician)
            ->postJson('/api/sync', [
                'last_synced_at' => now()->subHour()->toIso8601String(),
                'actions' => [
                    [
                        'type' => 'location_update',
                        'latitude' => 40.7128,
                        'longitude' => -74.0060,
                        'accuracy' => 10.0,
                        'timestamp' => now()->subMinutes(5)->toIso8601String(),
                    ],
                ],
            ]);

        $response->assertOk();
        expect(TechnicianLocation::count())->toBe(1);
    });

    it('technician can sync comments', function () {
        $job = ServiceJob::factory()->assigned($this->technician)->create();

        $response = $this->actingAs($this->technician)
            ->postJson('/api/sync', [
                'last_synced_at' => now()->subHour()->toIso8601String(),
                'actions' => [
                    [
                        'type' => 'comment',
                        'job_id' => $job->id,
                        'body' => 'Arrived on site, starting work.',
                        'timestamp' => now()->toIso8601String(),
                    ],
                ],
            ]);

        $response->assertOk();
        expect($response->json('processed'))->toBe(1);
        expect($job->comments()->count())->toBe(1);
    });

    it('processes multiple action types in one sync', function () {
        $job = ServiceJob::factory()->assigned($this->technician)->create();

        $response = $this->actingAs($this->technician)
            ->postJson('/api/sync', [
                'last_synced_at' => now()->subHour()->toIso8601String(),
                'actions' => [
                    [
                        'type' => 'location_update',
                        'latitude' => 40.7128,
                        'longitude' => -74.0060,
                        'timestamp' => now()->subMinutes(30)->toIso8601String(),
                    ],
                    [
                        'type' => 'status_update',
                        'job_id' => $job->id,
                        'status' => 'on_the_way',
                        'timestamp' => now()->subMinutes(25)->toIso8601String(),
                    ],
                    [
                        'type' => 'comment',
                        'job_id' => $job->id,
                        'body' => 'On my way to the site.',
                        'timestamp' => now()->subMinutes(24)->toIso8601String(),
                    ],
                ],
            ]);

        $response->assertOk();
        expect($response->json('processed'))->toBe(3);
        expect($response->json('errors'))->toBeEmpty();
        expect(TechnicianLocation::count())->toBe(1);
        expect($job->fresh()->status)->toBe('on_the_way');
        expect($job->comments()->count())->toBe(1);
    });

    it('returns updated jobs since last sync', function () {
        $lastSync = now()->subHour();

        $job = ServiceJob::factory()->assigned($this->technician)->create([
            'updated_at' => now()->subMinutes(30),
        ]);

        $response = $this->actingAs($this->technician)
            ->postJson('/api/sync', [
                'last_synced_at' => $lastSync->toIso8601String(),
                'actions' => [
                    [
                        'type' => 'location_update',
                        'latitude' => 40.7128,
                        'longitude' => -74.0060,
                        'timestamp' => now()->toIso8601String(),
                    ],
                ],
            ]);

        $response->assertOk();
        expect($response->json('updated_jobs'))->toHaveCount(1);
        expect($response->json('synced_at'))->not->toBeNull();
    });

    it('handles errors gracefully in batch', function () {
        $job = ServiceJob::factory()->assigned($this->technician)->create();

        $response = $this->actingAs($this->technician)
            ->postJson('/api/sync', [
                'last_synced_at' => now()->subHour()->toIso8601String(),
                'actions' => [
                    [
                        'type' => 'location_update',
                        'latitude' => 40.7128,
                        'longitude' => -74.0060,
                        'timestamp' => now()->toIso8601String(),
                    ],
                    [
                        'type' => 'status_update',
                        'job_id' => $job->id,
                        'status' => 'completed', // invalid: assigned → completed not allowed
                        'timestamp' => now()->toIso8601String(),
                    ],
                ],
            ]);

        $response->assertOk();
        // Location should succeed, status should fail
        expect($response->json('processed'))->toBe(1);
        expect($response->json('errors'))->toHaveCount(1);
        expect(TechnicianLocation::count())->toBe(1);
        expect($job->fresh()->status)->toBe('assigned'); // unchanged
    });

    it('non-technician cannot use sync endpoint', function () {
        $response = $this->actingAs($this->admin)
            ->postJson('/api/sync', [
                'last_synced_at' => now()->toIso8601String(),
                'actions' => [],
            ]);

        $response->assertForbidden();
    });

    it('technician cannot sync status for another technicians job', function () {
        $otherTech = User::factory()->create(['role' => 'technician']);
        $job = ServiceJob::factory()->assigned($otherTech)->create();

        $response = $this->actingAs($this->technician)
            ->postJson('/api/sync', [
                'last_synced_at' => now()->subHour()->toIso8601String(),
                'actions' => [
                    [
                        'type' => 'status_update',
                        'job_id' => $job->id,
                        'status' => 'on_the_way',
                        'timestamp' => now()->toIso8601String(),
                    ],
                ],
            ]);

        $response->assertOk();
        expect($response->json('errors'))->toHaveCount(1);
        expect($job->fresh()->status)->toBe('assigned');
    });
});

// ── Service Job Coordinates ────────────────────────────────────

describe('Job Coordinates', function () {
    it('job can be created with coordinates', function () {
        $response = $this->actingAs($this->admin)
            ->postJson('/api/service-jobs', [
                'customer_id' => $this->customer->id,
                'service_id' => $this->service->id,
                'address' => '350 Fifth Avenue, New York, NY',
                'scheduled_date' => now()->addDays(3)->toDateString(),
                'latitude' => 40.7484,
                'longitude' => -73.9857,
            ]);

        $response->assertCreated();
        expect($response->json('job.latitude'))->toBe('40.7484000');
        expect($response->json('job.longitude'))->toBe('-73.9857000');
    });

    it('job coordinates are included in response', function () {
        $job = ServiceJob::factory()->create([
            'latitude' => 40.7484,
            'longitude' => -73.9857,
        ]);

        $response = $this->actingAs($this->admin)
            ->getJson("/api/service-jobs/{$job->id}");

        $response->assertOk();
        expect($response->json('job.latitude'))->not->toBeNull();
        expect($response->json('job.longitude'))->not->toBeNull();
    });
});
