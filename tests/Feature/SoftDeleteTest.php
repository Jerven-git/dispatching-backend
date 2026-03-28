<?php

use App\Models\Customer;
use App\Models\ServiceJob;
use App\Models\User;

beforeEach(function () {
    $this->admin = User::factory()->create(['role' => 'admin']);
});

describe('Soft deletes', function () {
    it('soft deletes customers instead of hard deleting', function () {
        $customer = Customer::factory()->create();

        $response = $this->actingAs($this->admin)
            ->deleteJson("/api/customers/{$customer->id}");

        $response->assertOk();
        expect(Customer::find($customer->id))->toBeNull();
        expect(Customer::withTrashed()->find($customer->id))->not->toBeNull();
    });

    it('soft deletes service jobs', function () {
        $job = ServiceJob::factory()->create();

        $response = $this->actingAs($this->admin)
            ->deleteJson("/api/service-jobs/{$job->id}");

        $response->assertOk();
        expect(ServiceJob::find($job->id))->toBeNull();
        expect(ServiceJob::withTrashed()->find($job->id))->not->toBeNull();
    });

    it('soft deletes users', function () {
        $user = User::factory()->create(['role' => 'technician']);

        $response = $this->actingAs($this->admin)
            ->deleteJson("/api/users/{$user->id}");

        $response->assertOk();
        expect(User::find($user->id))->toBeNull();
        expect(User::withTrashed()->find($user->id))->not->toBeNull();
    });
});
