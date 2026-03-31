<?php

use App\Models\ChecklistItem;
use App\Models\JobAttachment;
use App\Models\JobComment;
use App\Models\Service;
use App\Models\ServiceJob;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->admin = User::factory()->create(['role' => 'admin']);
    $this->technician = User::factory()->create(['role' => 'technician']);
    $this->job = ServiceJob::factory()->assigned($this->technician)->create();
});

describe('Attachments', function () {
    it('uploads a file attachment', function () {
        Storage::fake('public');

        $response = $this->actingAs($this->technician)
            ->postJson("/api/service-jobs/{$this->job->id}/attachments", [
                'file' => UploadedFile::fake()->image('photo.jpg', 800, 600),
                'category' => 'before',
            ]);

        $response->assertCreated();
        expect($response->json('attachment.category'))->toBe('before');
        expect($response->json('attachment.file_name'))->toBe('photo.jpg');
        expect(JobAttachment::count())->toBe(1);
    });

    it('lists attachments for a job', function () {
        JobAttachment::create([
            'service_job_id' => $this->job->id,
            'uploaded_by' => $this->technician->id,
            'file_name' => 'test.jpg',
            'file_path' => 'test/path.jpg',
            'file_type' => 'image/jpeg',
            'file_size' => 1024,
            'category' => 'before',
        ]);

        $response = $this->actingAs($this->admin)
            ->getJson("/api/service-jobs/{$this->job->id}/attachments");

        $response->assertOk();
        expect($response->json('attachments'))->toHaveCount(1);
    });

    it('deletes an attachment', function () {
        Storage::fake('public');

        $attachment = JobAttachment::create([
            'service_job_id' => $this->job->id,
            'uploaded_by' => $this->admin->id,
            'file_name' => 'test.jpg',
            'file_path' => 'test/path.jpg',
            'file_type' => 'image/jpeg',
            'file_size' => 1024,
            'category' => 'other',
        ]);

        $response = $this->actingAs($this->admin)
            ->deleteJson("/api/service-jobs/{$this->job->id}/attachments/{$attachment->id}");

        $response->assertOk();
        expect(JobAttachment::count())->toBe(0);
    });

    it('rejects files over 10MB', function () {
        Storage::fake('public');

        $response = $this->actingAs($this->technician)
            ->postJson("/api/service-jobs/{$this->job->id}/attachments", [
                'file' => UploadedFile::fake()->create('large.pdf', 11000), // 11MB
            ]);

        $response->assertUnprocessable();
    });
});

describe('Checklist', function () {
    it('admin can create checklist items for a service', function () {
        $service = $this->job->service;

        $response = $this->actingAs($this->admin)
            ->postJson("/api/services/{$service->id}/checklist", [
                'label' => 'Check water pressure',
                'is_required' => true,
                'sort_order' => 1,
            ]);

        $response->assertCreated();
        expect(ChecklistItem::count())->toBe(1);
    });

    it('returns checklist with completion status for a job', function () {
        $service = $this->job->service;
        $item = ChecklistItem::create([
            'service_id' => $service->id,
            'label' => 'Inspect unit',
            'sort_order' => 0,
            'is_required' => true,
        ]);

        $response = $this->actingAs($this->technician)
            ->getJson("/api/service-jobs/{$this->job->id}/checklist");

        $response->assertOk();
        expect($response->json('checklist'))->toHaveCount(1);
        expect($response->json('checklist.0.is_completed'))->toBeFalse();
    });

    it('toggles a checklist item on and off', function () {
        $item = ChecklistItem::create([
            'service_id' => $this->job->service_id,
            'label' => 'Test item',
            'sort_order' => 0,
        ]);

        // Toggle on
        $response = $this->actingAs($this->technician)
            ->patchJson("/api/service-jobs/{$this->job->id}/checklist/{$item->id}/toggle");

        $response->assertOk();
        expect($response->json('entry.is_completed'))->toBeTrue();

        // Toggle off
        $response = $this->actingAs($this->technician)
            ->patchJson("/api/service-jobs/{$this->job->id}/checklist/{$item->id}/toggle");

        $response->assertOk();
        expect($response->json('entry.is_completed'))->toBeFalse();
    });

    it('admin can delete a checklist item', function () {
        $item = ChecklistItem::create([
            'service_id' => $this->job->service_id,
            'label' => 'To delete',
            'sort_order' => 0,
        ]);

        $response = $this->actingAs($this->admin)
            ->deleteJson("/api/services/{$this->job->service_id}/checklist/{$item->id}");

        $response->assertOk();
        expect(ChecklistItem::count())->toBe(0);
    });
});

describe('Signature', function () {
    it('saves customer signature on a job', function () {
        Storage::fake('public');

        // Minimal valid base64 PNG
        $base64 = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==';

        $response = $this->actingAs($this->technician)
            ->postJson("/api/service-jobs/{$this->job->id}/signature", [
                'signature' => $base64,
                'signed_by_name' => 'John Customer',
            ]);

        $response->assertOk();
        $fresh = $this->job->fresh();
        expect($fresh->signed_by_name)->toBe('John Customer');
        expect($fresh->signed_at)->not->toBeNull();
        expect($fresh->signature_path)->not->toBeNull();
    });

    it('requires signature data and name', function () {
        $response = $this->actingAs($this->technician)
            ->postJson("/api/service-jobs/{$this->job->id}/signature", []);

        $response->assertUnprocessable();
    });
});

describe('Comments', function () {
    it('adds a comment to a job', function () {
        $response = $this->actingAs($this->admin)
            ->postJson("/api/service-jobs/{$this->job->id}/comments", [
                'body' => 'Customer requested morning appointment.',
                'is_internal' => true,
            ]);

        $response->assertCreated();
        expect($response->json('comment.body'))->toBe('Customer requested morning appointment.');
        expect($response->json('comment.is_internal'))->toBeTrue();
    });

    it('lists comments for a job', function () {
        JobComment::create([
            'service_job_id' => $this->job->id,
            'user_id' => $this->admin->id,
            'body' => 'First comment',
        ]);
        JobComment::create([
            'service_job_id' => $this->job->id,
            'user_id' => $this->technician->id,
            'body' => 'Second comment',
        ]);

        $response = $this->actingAs($this->admin)
            ->getJson("/api/service-jobs/{$this->job->id}/comments");

        $response->assertOk();
        expect($response->json('comments'))->toHaveCount(2);
    });

    it('user can delete their own comment', function () {
        $comment = JobComment::create([
            'service_job_id' => $this->job->id,
            'user_id' => $this->technician->id,
            'body' => 'My comment',
        ]);

        $response = $this->actingAs($this->technician)
            ->deleteJson("/api/service-jobs/{$this->job->id}/comments/{$comment->id}");

        $response->assertOk();
        expect(JobComment::withTrashed()->find($comment->id)->trashed())->toBeTrue();
    });

    it('user cannot delete someone elses comment', function () {
        $comment = JobComment::create([
            'service_job_id' => $this->job->id,
            'user_id' => $this->admin->id,
            'body' => 'Admin comment',
        ]);

        $response = $this->actingAs($this->technician)
            ->deleteJson("/api/service-jobs/{$this->job->id}/comments/{$comment->id}");

        $response->assertForbidden();
    });

    it('admin can delete any comment', function () {
        $comment = JobComment::create([
            'service_job_id' => $this->job->id,
            'user_id' => $this->technician->id,
            'body' => 'Tech comment',
        ]);

        $response = $this->actingAs($this->admin)
            ->deleteJson("/api/service-jobs/{$this->job->id}/comments/{$comment->id}");

        $response->assertOk();
    });
});

describe('Clone Job', function () {
    it('clones a job with a new date', function () {
        $response = $this->actingAs($this->admin)
            ->postJson("/api/service-jobs/{$this->job->id}/clone", [
                'scheduled_date' => now()->addDays(7)->toDateString(),
            ]);

        $response->assertCreated();
        $clone = $response->json('job');
        expect($clone['customer']['id'])->toBe($this->job->customer_id);
        expect($clone['service']['id'])->toBe($this->job->service_id);
        expect($clone['address'])->toBe($this->job->address);
        expect($clone['id'])->not->toBe($this->job->id);
        expect($clone['reference_number'])->not->toBe($this->job->reference_number);
    });

    it('requires a scheduled date', function () {
        $response = $this->actingAs($this->admin)
            ->postJson("/api/service-jobs/{$this->job->id}/clone", []);

        $response->assertUnprocessable();
    });

    it('sets correct status based on technician', function () {
        $response = $this->actingAs($this->admin)
            ->postJson("/api/service-jobs/{$this->job->id}/clone", [
                'scheduled_date' => now()->addDays(3)->toDateString(),
            ]);

        // Original job has a technician, so clone should be 'assigned'
        expect($response->json('job.status'))->toBe('assigned');
    });
});
