<?php

use App\Models\Customer;
use App\Models\Service;
use App\Models\ServiceJob;
use App\Models\User;

beforeEach(function () {
    $this->admin = User::factory()->create(['role' => 'admin']);
    $this->dispatcher = User::factory()->create(['role' => 'dispatcher']);
    $this->technician = User::factory()->create(['role' => 'technician']);
});

describe('Role-based route protection', function () {
    it('blocks unauthenticated access to protected routes', function () {
        $this->getJson('/api/dashboard')->assertUnauthorized();
        $this->getJson('/api/service-jobs')->assertUnauthorized();
        $this->getJson('/api/customers')->assertUnauthorized();
        $this->getJson('/api/users')->assertUnauthorized();
    });

    it('blocks technician from admin-only routes', function () {
        $this->actingAs($this->technician);

        $this->getJson('/api/users')->assertForbidden();
        $this->getJson('/api/services')->assertForbidden();
        $this->getJson('/api/customers')->assertForbidden();
    });

    it('blocks technician from dispatcher routes', function () {
        $this->actingAs($this->technician);

        $this->getJson('/api/service-jobs')->assertForbidden();
        $this->postJson('/api/service-jobs', [])->assertForbidden();
    });

    it('blocks dispatcher from admin-only routes', function () {
        $this->actingAs($this->dispatcher);

        $this->getJson('/api/users')->assertForbidden();
        $this->getJson('/api/services')->assertForbidden();
    });

    it('allows admin access to all routes', function () {
        $this->actingAs($this->admin);

        $this->getJson('/api/dashboard')->assertOk();
        $this->getJson('/api/users')->assertOk();
        $this->getJson('/api/services')->assertOk();
        $this->getJson('/api/customers')->assertOk();
        $this->getJson('/api/service-jobs')->assertOk();
    });

    it('allows dispatcher access to dispatcher routes', function () {
        $this->actingAs($this->dispatcher);

        $this->getJson('/api/dashboard')->assertOk();
        $this->getJson('/api/customers')->assertOk();
        $this->getJson('/api/service-jobs')->assertOk();
    });

    it('allows technician access to my-jobs', function () {
        $this->actingAs($this->technician);

        $this->getJson('/api/my-jobs')->assertOk();
        $this->getJson('/api/dashboard')->assertOk();
    });
});

describe('Technician job scoping', function () {
    it('technician only sees their own assigned jobs in my-jobs', function () {
        $otherTech = User::factory()->create(['role' => 'technician']);
        ServiceJob::factory()->assigned($this->technician)->count(2)->create();
        ServiceJob::factory()->assigned($otherTech)->count(3)->create();

        $response = $this->actingAs($this->technician)
            ->getJson('/api/my-jobs');

        $response->assertOk();
        expect($response->json('data'))->toHaveCount(2);
    });
});

describe('User CRUD authorization', function () {
    it('allows admin to create users', function () {
        $response = $this->actingAs($this->admin)
            ->postJson('/api/users', [
                'name' => 'New Tech',
                'email' => 'newtech@example.com',
                'password' => 'password123',
                'role' => 'technician',
            ]);

        $response->assertCreated();
        expect($response->json('user.name'))->toBe('New Tech');
    });

    it('blocks dispatcher from creating users', function () {
        $response = $this->actingAs($this->dispatcher)
            ->postJson('/api/users', [
                'name' => 'New User',
                'email' => 'new@example.com',
                'password' => 'password123',
                'role' => 'technician',
            ]);

        $response->assertForbidden();
    });

    it('allows admin to update users', function () {
        $user = User::factory()->create(['role' => 'technician']);

        $response = $this->actingAs($this->admin)
            ->putJson("/api/users/{$user->id}", [
                'name' => 'Updated Name',
            ]);

        $response->assertOk();
        expect($user->fresh()->name)->toBe('Updated Name');
    });

    it('allows admin to delete users', function () {
        $user = User::factory()->create(['role' => 'technician']);

        $response = $this->actingAs($this->admin)
            ->deleteJson("/api/users/{$user->id}");

        $response->assertOk();
        expect($user->fresh()->trashed())->toBeTrue();
    });

    it('prevents admin from deleting themselves', function () {
        $response = $this->actingAs($this->admin)
            ->deleteJson("/api/users/{$this->admin->id}");

        $response->assertForbidden();
    });
});

describe('Login rate limiting', function () {
    it('blocks login after too many attempts', function () {
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/login', [
                'email' => 'nonexistent@example.com',
                'password' => 'wrong',
            ]);
        }

        $response = $this->postJson('/api/login', [
            'email' => 'nonexistent@example.com',
            'password' => 'wrong',
        ]);

        $response->assertTooManyRequests();
    });
});
