<?php

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\JobReview;
use App\Models\Service;
use App\Models\ServiceJob;
use App\Models\ServiceRequest;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    $this->admin = User::factory()->create(['role' => 'admin']);
    $this->technician = User::factory()->create(['role' => 'technician']);
    $this->customer = Customer::factory()->create([
        'password' => Hash::make('customer123'),
        'portal_access' => true,
    ]);
});

describe('Customer Auth', function () {
    it('customer can login to portal', function () {
        $response = $this->postJson('/api/portal/login', [
            'email' => $this->customer->email,
            'password' => 'customer123',
        ]);

        $response->assertOk();
        expect($response->json('customer.name'))->toBe($this->customer->name);
    });

    it('rejects invalid credentials', function () {
        $response = $this->postJson('/api/portal/login', [
            'email' => $this->customer->email,
            'password' => 'wrong',
        ]);

        $response->assertUnauthorized();
    });

    it('rejects customer without portal access', function () {
        $this->customer->update(['portal_access' => false]);

        $response = $this->postJson('/api/portal/login', [
            'email' => $this->customer->email,
            'password' => 'customer123',
        ]);

        $response->assertUnauthorized();
    });

    it('returns current customer via /me', function () {
        $this->actingAs($this->customer, 'customer');

        $response = $this->getJson('/api/portal/me');

        $response->assertOk();
        expect($response->json('customer.id'))->toBe($this->customer->id);
    });

    it('blocks unauthenticated portal access', function () {
        $this->getJson('/api/portal/jobs')->assertUnauthorized();
        $this->getJson('/api/portal/invoices')->assertUnauthorized();
    });
});

describe('Customer Jobs', function () {
    it('customer sees only their own jobs', function () {
        $otherCustomer = Customer::factory()->create();
        ServiceJob::factory()->count(3)->create(['customer_id' => $this->customer->id]);
        ServiceJob::factory()->count(2)->create(['customer_id' => $otherCustomer->id]);

        $response = $this->actingAs($this->customer, 'customer')
            ->getJson('/api/portal/jobs');

        $response->assertOk();
        expect($response->json('data'))->toHaveCount(3);
    });

    it('customer can view a specific job', function () {
        $job = ServiceJob::factory()->create(['customer_id' => $this->customer->id]);

        $response = $this->actingAs($this->customer, 'customer')
            ->getJson("/api/portal/jobs/{$job->id}");

        $response->assertOk();
        expect($response->json('job.id'))->toBe($job->id);
    });

    it('customer cannot view another customers job', function () {
        $otherJob = ServiceJob::factory()->create();

        $response = $this->actingAs($this->customer, 'customer')
            ->getJson("/api/portal/jobs/{$otherJob->id}");

        $response->assertNotFound();
    });
});

describe('Customer Invoices', function () {
    it('customer sees only their invoices', function () {
        $job = ServiceJob::factory()->completed($this->technician)->create([
            'customer_id' => $this->customer->id,
        ]);
        Invoice::factory()->count(2)->create([
            'customer_id' => $this->customer->id,
            'service_job_id' => $job->id,
            'created_by' => $this->admin->id,
        ]);
        Invoice::factory()->create(['created_by' => $this->admin->id]);

        $response = $this->actingAs($this->customer, 'customer')
            ->getJson('/api/portal/invoices');

        $response->assertOk();
        expect($response->json('data'))->toHaveCount(2);
    });
});

describe('Service Requests', function () {
    it('customer can submit a service request', function () {
        $service = Service::factory()->create();

        $response = $this->actingAs($this->customer, 'customer')
            ->postJson('/api/portal/service-requests', [
                'service_id' => $service->id,
                'description' => 'My AC is not cooling properly.',
                'preferred_date' => now()->addDays(3)->toDateString(),
                'address' => '123 Customer St',
            ]);

        $response->assertCreated();
        expect(ServiceRequest::count())->toBe(1);
        expect(ServiceRequest::first()->status)->toBe('pending');
    });

    it('customer can view their requests', function () {
        ServiceRequest::create([
            'customer_id' => $this->customer->id,
            'description' => 'Test request',
            'address' => '123 Test St',
        ]);

        $response = $this->actingAs($this->customer, 'customer')
            ->getJson('/api/portal/service-requests');

        $response->assertOk();
        expect($response->json('requests'))->toHaveCount(1);
    });

    it('admin can approve a service request and create a job', function () {
        $service = Service::factory()->create();
        $request = ServiceRequest::create([
            'customer_id' => $this->customer->id,
            'service_id' => $service->id,
            'description' => 'Fix my heater',
            'address' => '456 Cold St',
        ]);

        $response = $this->actingAs($this->admin)
            ->postJson("/api/service-requests/{$request->id}/approve", [
                'scheduled_date' => now()->addDays(2)->toDateString(),
                'technician_id' => $this->technician->id,
            ]);

        $response->assertOk();
        expect($request->fresh()->status)->toBe('approved');
        expect($request->fresh()->converted_job_id)->not->toBeNull();
        expect(ServiceJob::count())->toBe(1);
    });

    it('admin can decline a service request', function () {
        $request = ServiceRequest::create([
            'customer_id' => $this->customer->id,
            'description' => 'Something',
            'address' => '789 Nope St',
        ]);

        $response = $this->actingAs($this->admin)
            ->postJson("/api/service-requests/{$request->id}/decline", [
                'admin_notes' => 'Service not available in your area.',
            ]);

        $response->assertOk();
        expect($request->fresh()->status)->toBe('declined');
    });
});

describe('Reviews', function () {
    it('customer can review a completed job', function () {
        $job = ServiceJob::factory()->completed($this->technician)->create([
            'customer_id' => $this->customer->id,
        ]);

        $response = $this->actingAs($this->customer, 'customer')
            ->postJson("/api/portal/jobs/{$job->id}/review", [
                'rating' => 5,
                'comment' => 'Excellent service!',
            ]);

        $response->assertCreated();
        expect(JobReview::count())->toBe(1);
        expect($response->json('review.rating'))->toBe(5);
    });

    it('customer cannot review a non-completed job', function () {
        $job = ServiceJob::factory()->assigned($this->technician)->create([
            'customer_id' => $this->customer->id,
        ]);

        $response = $this->actingAs($this->customer, 'customer')
            ->postJson("/api/portal/jobs/{$job->id}/review", [
                'rating' => 4,
            ]);

        $response->assertNotFound();
    });

    it('customer cannot review the same job twice', function () {
        $job = ServiceJob::factory()->completed($this->technician)->create([
            'customer_id' => $this->customer->id,
        ]);

        JobReview::create([
            'service_job_id' => $job->id,
            'customer_id' => $this->customer->id,
            'rating' => 5,
        ]);

        $response = $this->actingAs($this->customer, 'customer')
            ->postJson("/api/portal/jobs/{$job->id}/review", [
                'rating' => 3,
            ]);

        $response->assertUnprocessable();
    });

    it('customer can view their reviews', function () {
        $job = ServiceJob::factory()->completed($this->technician)->create([
            'customer_id' => $this->customer->id,
        ]);
        JobReview::create([
            'service_job_id' => $job->id,
            'customer_id' => $this->customer->id,
            'rating' => 4,
            'comment' => 'Good job!',
        ]);

        $response = $this->actingAs($this->customer, 'customer')
            ->getJson('/api/portal/reviews');

        $response->assertOk();
        expect($response->json('reviews'))->toHaveCount(1);
    });

    it('validates rating is 1-5', function () {
        $job = ServiceJob::factory()->completed($this->technician)->create([
            'customer_id' => $this->customer->id,
        ]);

        $response = $this->actingAs($this->customer, 'customer')
            ->postJson("/api/portal/jobs/{$job->id}/review", [
                'rating' => 6,
            ]);

        $response->assertUnprocessable();
    });
});

describe('Available Services', function () {
    it('customer can browse active services', function () {
        Service::factory()->count(3)->create(['is_active' => true]);
        Service::factory()->create(['is_active' => false]);

        $response = $this->actingAs($this->customer, 'customer')
            ->getJson('/api/portal/services');

        $response->assertOk();
        expect($response->json('services'))->toHaveCount(3);
    });
});
