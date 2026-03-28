<?php

use App\Models\ServiceJob;
use App\Models\User;
use App\Services\StatusTransitionService;

beforeEach(function () {
    $this->admin = User::factory()->create(['role' => 'admin']);
    $this->technician = User::factory()->create(['role' => 'technician']);
});

describe('StatusTransitionService', function () {
    it('allows valid transitions', function () {
        $service = app(StatusTransitionService::class);

        // Should not throw
        $service->validate('pending', 'assigned');
        $service->validate('pending', 'cancelled');
        $service->validate('assigned', 'on_the_way');
        $service->validate('assigned', 'in_progress');
        $service->validate('assigned', 'pending');
        $service->validate('on_the_way', 'in_progress');
        $service->validate('in_progress', 'completed');
        $service->validate('in_progress', 'cancelled');

        expect(true)->toBeTrue();
    });

    it('rejects invalid transitions', function (string $from, string $to) {
        $service = app(StatusTransitionService::class);

        expect(fn () => $service->validate($from, $to))
            ->toThrow(\Illuminate\Validation\ValidationException::class);
    })->with([
        ['pending', 'in_progress'],
        ['pending', 'completed'],
        ['pending', 'on_the_way'],
        ['completed', 'pending'],
        ['completed', 'in_progress'],
        ['cancelled', 'pending'],
        ['cancelled', 'assigned'],
        ['on_the_way', 'assigned'],
        ['on_the_way', 'completed'],
    ]);

    it('allows same status (no-op)', function () {
        $service = app(StatusTransitionService::class);

        $service->validate('pending', 'pending');
        $service->validate('completed', 'completed');

        expect(true)->toBeTrue();
    });
});

describe('Status Transition API', function () {
    it('allows admin to transition job status via API', function () {
        $job = ServiceJob::factory()->assigned($this->technician)->create();

        $response = $this->actingAs($this->admin)
            ->patchJson("/api/service-jobs/{$job->id}/status", [
                'status' => 'in_progress',
            ]);

        $response->assertOk();
        expect($job->fresh()->status)->toBe('in_progress');
        expect($job->fresh()->started_at)->not->toBeNull();
    });

    it('rejects invalid status transition via API', function () {
        $job = ServiceJob::factory()->create(['status' => 'pending']);

        $response = $this->actingAs($this->admin)
            ->patchJson("/api/service-jobs/{$job->id}/status", [
                'status' => 'completed',
            ]);

        $response->assertUnprocessable();
        expect($job->fresh()->status)->toBe('pending');
    });

    it('rejects transition from terminal status', function () {
        $job = ServiceJob::factory()->completed($this->technician)->create();

        $response = $this->actingAs($this->admin)
            ->patchJson("/api/service-jobs/{$job->id}/status", [
                'status' => 'pending',
            ]);

        $response->assertUnprocessable();
        expect($job->fresh()->status)->toBe('completed');
    });

    it('sets completed_at when completing a job', function () {
        $job = ServiceJob::factory()->inProgress($this->technician)->create();

        $this->actingAs($this->admin)
            ->patchJson("/api/service-jobs/{$job->id}/status", [
                'status' => 'completed',
            ]);

        $fresh = $job->fresh();
        expect($fresh->status)->toBe('completed');
        expect($fresh->completed_at)->not->toBeNull();
    });

    it('sets cancelled_at when cancelling a job', function () {
        $job = ServiceJob::factory()->assigned($this->technician)->create();

        $this->actingAs($this->admin)
            ->patchJson("/api/service-jobs/{$job->id}/status", [
                'status' => 'cancelled',
            ]);

        $fresh = $job->fresh();
        expect($fresh->status)->toBe('cancelled');
        expect($fresh->cancelled_at)->not->toBeNull();
    });

    it('creates a status log entry on transition', function () {
        $job = ServiceJob::factory()->assigned($this->technician)->create();

        $this->actingAs($this->admin)
            ->patchJson("/api/service-jobs/{$job->id}/status", [
                'status' => 'on_the_way',
            ]);

        expect($job->statusLogs()->count())->toBe(1);
        $log = $job->statusLogs()->first();
        expect($log->old_status)->toBe('assigned');
        expect($log->new_status)->toBe('on_the_way');
        expect($log->changed_by)->toBe($this->admin->id);
    });
});

describe('Technician Status Updates', function () {
    it('allows technician to update their own job status', function () {
        $job = ServiceJob::factory()->assigned($this->technician)->create();

        $response = $this->actingAs($this->technician)
            ->patchJson("/api/my-jobs/{$job->id}/status", [
                'status' => 'on_the_way',
            ]);

        $response->assertOk();
        expect($job->fresh()->status)->toBe('on_the_way');
    });

    it('rejects technician updating another technicians job', function () {
        $otherTechnician = User::factory()->create(['role' => 'technician']);
        $job = ServiceJob::factory()->assigned($otherTechnician)->create();

        $response = $this->actingAs($this->technician)
            ->patchJson("/api/my-jobs/{$job->id}/status", [
                'status' => 'on_the_way',
            ]);

        $response->assertForbidden();
    });
});
