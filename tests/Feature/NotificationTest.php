<?php

use App\Models\ServiceJob;
use App\Models\User;
use App\Notifications\JobAssigned;
use App\Notifications\JobStatusChanged;
use App\Notifications\CustomerJobUpdate;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    $this->admin = User::factory()->create(['role' => 'admin']);
    $this->dispatcher = User::factory()->create(['role' => 'dispatcher']);
    $this->technician = User::factory()->create(['role' => 'technician']);
});

describe('Job Assignment Notifications', function () {
    it('notifies technician when assigned to a job', function () {
        Notification::fake();

        $job = ServiceJob::factory()->create(['status' => 'pending']);

        $this->actingAs($this->admin)
            ->patchJson("/api/service-jobs/{$job->id}/assign", [
                'technician_id' => $this->technician->id,
            ]);

        Notification::assertSentTo($this->technician, JobAssigned::class);
    });

    it('does not notify when unassigning technician', function () {
        Notification::fake();

        $job = ServiceJob::factory()->assigned($this->technician)->create();

        $this->actingAs($this->admin)
            ->patchJson("/api/service-jobs/{$job->id}/assign", [
                'technician_id' => null,
            ]);

        Notification::assertNotSentTo($this->technician, JobAssigned::class);
    });
});

describe('Status Change Notifications', function () {
    it('notifies admins and dispatchers when job is completed', function () {
        Notification::fake();

        $job = ServiceJob::factory()->inProgress($this->technician)->create();

        $this->actingAs($this->technician)
            ->patchJson("/api/my-jobs/{$job->id}/status", [
                'status' => 'completed',
            ]);

        Notification::assertSentTo($this->admin, JobStatusChanged::class);
        Notification::assertSentTo($this->dispatcher, JobStatusChanged::class);
    });

    it('notifies admins and dispatchers when job is cancelled', function () {
        Notification::fake();

        $job = ServiceJob::factory()->assigned($this->technician)->create();

        $this->actingAs($this->admin)
            ->patchJson("/api/service-jobs/{$job->id}/status", [
                'status' => 'cancelled',
            ]);

        // Admin made the change, so only dispatcher should be notified
        Notification::assertNotSentTo($this->admin, JobStatusChanged::class);
        Notification::assertSentTo($this->dispatcher, JobStatusChanged::class);
    });

    it('notifies customer when technician is on the way', function () {
        Notification::fake();

        $job = ServiceJob::factory()->assigned($this->technician)->create();

        $this->actingAs($this->technician)
            ->patchJson("/api/my-jobs/{$job->id}/status", [
                'status' => 'on_the_way',
            ]);

        Notification::assertSentTo($job->customer, CustomerJobUpdate::class);
    });

    it('notifies customer when job is completed', function () {
        Notification::fake();

        $job = ServiceJob::factory()->inProgress($this->technician)->create();

        $this->actingAs($this->technician)
            ->patchJson("/api/my-jobs/{$job->id}/status", [
                'status' => 'completed',
            ]);

        Notification::assertSentTo($job->customer, CustomerJobUpdate::class);
    });

    it('does not notify the person who made the change', function () {
        Notification::fake();

        $job = ServiceJob::factory()->inProgress($this->technician)->create();

        $this->actingAs($this->technician)
            ->patchJson("/api/my-jobs/{$job->id}/status", [
                'status' => 'completed',
            ]);

        // Technician made the change, should not get a JobStatusChanged
        Notification::assertNotSentTo(
            $this->technician,
            JobStatusChanged::class
        );
    });
});

describe('Notification API', function () {
    it('returns user notifications', function () {
        Notification::fake();

        $job = ServiceJob::factory()->assigned($this->technician)->create();
        $this->technician->notify(new JobAssigned($job));

        Notification::assertSentTo($this->technician, JobAssigned::class);

        // Now test the API with a real notification
        Notification::spy(); // restore real behavior
        $this->technician->notifications()->delete(); // clean slate
        $this->technician->notify(new JobAssigned($job));

        $response = $this->actingAs($this->technician)
            ->getJson('/api/notifications');

        $response->assertOk();
        expect(count($response->json('data')))->toBeGreaterThanOrEqual(1);
    });

    it('returns unread count', function () {
        $this->technician->notifications()->delete();

        // Insert notification records directly
        $this->technician->notifications()->create([
            'id' => \Illuminate\Support\Str::uuid(),
            'type' => JobAssigned::class,
            'data' => ['title' => 'Test', 'message' => 'Test', 'type' => 'job_assigned'],
        ]);
        $this->technician->notifications()->create([
            'id' => \Illuminate\Support\Str::uuid(),
            'type' => JobAssigned::class,
            'data' => ['title' => 'Test 2', 'message' => 'Test 2', 'type' => 'job_assigned'],
        ]);

        $response = $this->actingAs($this->technician)
            ->getJson('/api/notifications/unread-count');

        $response->assertOk();
        expect($response->json('count'))->toBe(2);
    });

    it('marks a notification as read', function () {
        $this->technician->notifications()->delete();

        $id = \Illuminate\Support\Str::uuid()->toString();
        $this->technician->notifications()->create([
            'id' => $id,
            'type' => JobAssigned::class,
            'data' => ['title' => 'Test', 'message' => 'Test', 'type' => 'job_assigned'],
        ]);

        $response = $this->actingAs($this->technician)
            ->patchJson("/api/notifications/{$id}/read");

        $response->assertOk();
        expect($this->technician->notifications()->find($id)->read_at)->not->toBeNull();
    });

    it('marks all notifications as read', function () {
        $this->technician->notifications()->delete();

        $this->technician->notifications()->create([
            'id' => \Illuminate\Support\Str::uuid(),
            'type' => JobAssigned::class,
            'data' => ['title' => 'Test', 'message' => 'Test', 'type' => 'job_assigned'],
        ]);
        $this->technician->notifications()->create([
            'id' => \Illuminate\Support\Str::uuid(),
            'type' => JobAssigned::class,
            'data' => ['title' => 'Test 2', 'message' => 'Test 2', 'type' => 'job_assigned'],
        ]);

        $response = $this->actingAs($this->technician)
            ->postJson('/api/notifications/mark-all-read');

        $response->assertOk();
        expect($this->technician->unreadNotifications()->count())->toBe(0);
    });
});
