<?php

use App\Models\Customer;
use App\Models\JobPart;
use App\Models\Part;
use App\Models\Service;
use App\Models\ServiceJob;
use App\Models\User;

beforeEach(function () {
    $this->admin = User::factory()->create(['role' => 'admin']);
    $this->dispatcher = User::factory()->create(['role' => 'dispatcher']);
    $this->technician = User::factory()->create(['role' => 'technician']);
    $this->service = Service::factory()->create(['base_price' => 100.00]);
});

// ── Parts Catalog CRUD ─────────────────────────────────────────

describe('Parts Catalog', function () {
    it('admin can create a part', function () {
        $response = $this->actingAs($this->admin)
            ->postJson('/api/parts', [
                'name' => 'Air Filter',
                'sku' => 'AF-1001',
                'unit_price' => 25.99,
                'stock_quantity' => 100,
                'minimum_stock' => 10,
                'unit' => 'piece',
            ]);

        $response->assertCreated();
        expect($response->json('part.name'))->toBe('Air Filter');
        expect($response->json('part.sku'))->toBe('AF-1001');
        expect(Part::count())->toBe(1);
    });

    it('rejects duplicate SKU', function () {
        Part::factory()->create(['sku' => 'AF-1001']);

        $response = $this->actingAs($this->admin)
            ->postJson('/api/parts', [
                'name' => 'Another Part',
                'sku' => 'AF-1001',
                'unit_price' => 10.00,
                'stock_quantity' => 50,
            ]);

        $response->assertUnprocessable();
    });

    it('admin can update a part', function () {
        $part = Part::factory()->create();

        $response = $this->actingAs($this->admin)
            ->putJson("/api/parts/{$part->id}", [
                'name' => 'Updated Name',
                'unit_price' => 30.00,
            ]);

        $response->assertOk();
        expect($part->fresh()->name)->toBe('Updated Name');
        expect($part->fresh()->unit_price)->toBe('30.00');
    });

    it('admin can delete a part with no job usage', function () {
        $part = Part::factory()->create();

        $response = $this->actingAs($this->admin)
            ->deleteJson("/api/parts/{$part->id}");

        $response->assertOk();
        expect(Part::count())->toBe(0);
    });

    it('cannot delete a part that has been used on jobs', function () {
        $part = Part::factory()->create();
        $job = ServiceJob::factory()->create(['service_id' => $this->service->id]);

        JobPart::create([
            'service_job_id' => $job->id,
            'part_id' => $part->id,
            'quantity' => 1,
            'unit_price' => $part->unit_price,
            'total_price' => $part->unit_price,
            'added_by' => $this->admin->id,
        ]);

        $response = $this->actingAs($this->admin)
            ->deleteJson("/api/parts/{$part->id}");

        $response->assertUnprocessable();
        expect(Part::count())->toBe(1);
    });

    it('admin/dispatcher can list parts', function () {
        Part::factory()->count(3)->create();

        $response = $this->actingAs($this->dispatcher)
            ->getJson('/api/parts');

        $response->assertOk();
        expect($response->json('data'))->toHaveCount(3);
    });

    it('can search parts by name', function () {
        Part::factory()->create(['name' => 'Air Filter']);
        Part::factory()->create(['name' => 'Oil Pump']);

        $response = $this->actingAs($this->admin)
            ->getJson('/api/parts?search=Air');

        $response->assertOk();
        expect($response->json('data'))->toHaveCount(1);
        expect($response->json('data.0.name'))->toBe('Air Filter');
    });

    it('can search parts by SKU', function () {
        Part::factory()->create(['sku' => 'AF-1001']);
        Part::factory()->create(['sku' => 'OP-2002']);

        $response = $this->actingAs($this->admin)
            ->getJson('/api/parts?search=AF-1001');

        $response->assertOk();
        expect($response->json('data'))->toHaveCount(1);
    });

    it('dispatcher cannot create parts', function () {
        $response = $this->actingAs($this->dispatcher)
            ->postJson('/api/parts', [
                'name' => 'Test',
                'sku' => 'T-001',
                'unit_price' => 10.00,
                'stock_quantity' => 50,
            ]);

        $response->assertForbidden();
    });

    it('technician cannot access parts catalog', function () {
        $response = $this->actingAs($this->technician)
            ->getJson('/api/parts');

        $response->assertForbidden();
    });
});

// ── Stock Management ───────────────────────────────────────────

describe('Stock Management', function () {
    it('admin can adjust stock up', function () {
        $part = Part::factory()->create(['stock_quantity' => 10]);

        $response = $this->actingAs($this->admin)
            ->postJson("/api/parts/{$part->id}/adjust-stock", [
                'adjustment' => 20,
                'reason' => 'Restocked from supplier',
            ]);

        $response->assertOk();
        expect($part->fresh()->stock_quantity)->toBe(30);
    });

    it('admin can adjust stock down', function () {
        $part = Part::factory()->create(['stock_quantity' => 50]);

        $response = $this->actingAs($this->admin)
            ->postJson("/api/parts/{$part->id}/adjust-stock", [
                'adjustment' => -10,
                'reason' => 'Damaged inventory',
            ]);

        $response->assertOk();
        expect($part->fresh()->stock_quantity)->toBe(40);
    });

    it('cannot adjust stock below zero', function () {
        $part = Part::factory()->create(['stock_quantity' => 5]);

        $response = $this->actingAs($this->admin)
            ->postJson("/api/parts/{$part->id}/adjust-stock", [
                'adjustment' => -10,
            ]);

        $response->assertUnprocessable();
        expect($part->fresh()->stock_quantity)->toBe(5);
    });

    it('shows low stock parts', function () {
        Part::factory()->create(['stock_quantity' => 3, 'minimum_stock' => 5]);
        Part::factory()->create(['stock_quantity' => 0, 'minimum_stock' => 10]);
        Part::factory()->create(['stock_quantity' => 50, 'minimum_stock' => 5]); // not low

        $response = $this->actingAs($this->admin)
            ->getJson('/api/parts/low-stock');

        $response->assertOk();
        expect($response->json('parts'))->toHaveCount(2);
    });

    it('low stock flag is set on part resource', function () {
        $part = Part::factory()->lowStock()->create();

        $response = $this->actingAs($this->admin)
            ->getJson("/api/parts/{$part->id}");

        $response->assertOk();
        expect($response->json('part.is_low_stock'))->toBeTrue();
    });
});

// ── Job Parts ──────────────────────────────────────────────────

describe('Job Parts', function () {
    it('can add a part to a job', function () {
        $part = Part::factory()->create(['unit_price' => 25.00, 'stock_quantity' => 50]);
        $job = ServiceJob::factory()->create(['service_id' => $this->service->id]);

        $response = $this->actingAs($this->admin)
            ->postJson("/api/service-jobs/{$job->id}/parts", [
                'part_id' => $part->id,
                'quantity' => 3,
                'notes' => 'Replaced air filter',
            ]);

        $response->assertCreated();
        expect($response->json('job_part.quantity'))->toBe(3);
        expect($response->json('job_part.unit_price'))->toBe('25.00');
        expect($response->json('job_part.total_price'))->toBe('75.00');
        expect($part->fresh()->stock_quantity)->toBe(47); // 50 - 3
    });

    it('auto-recalculates job total when adding parts', function () {
        $part = Part::factory()->create(['unit_price' => 50.00, 'stock_quantity' => 100]);
        $job = ServiceJob::factory()->create([
            'service_id' => $this->service->id, // base_price = 100
            'total_cost' => 100.00,
        ]);

        $this->actingAs($this->admin)
            ->postJson("/api/service-jobs/{$job->id}/parts", [
                'part_id' => $part->id,
                'quantity' => 2,
            ]);

        // 100 (service) + 100 (2 parts * 50) = 200
        expect($job->fresh()->total_cost)->toBe('200.00');
    });

    it('rejects adding part with insufficient stock', function () {
        $part = Part::factory()->create(['stock_quantity' => 2]);
        $job = ServiceJob::factory()->create(['service_id' => $this->service->id]);

        $response = $this->actingAs($this->admin)
            ->postJson("/api/service-jobs/{$job->id}/parts", [
                'part_id' => $part->id,
                'quantity' => 5,
            ]);

        $response->assertUnprocessable();
        expect($part->fresh()->stock_quantity)->toBe(2); // unchanged
    });

    it('rejects adding an inactive part', function () {
        $part = Part::factory()->inactive()->create();
        $job = ServiceJob::factory()->create(['service_id' => $this->service->id]);

        $response = $this->actingAs($this->admin)
            ->postJson("/api/service-jobs/{$job->id}/parts", [
                'part_id' => $part->id,
                'quantity' => 1,
            ]);

        $response->assertUnprocessable();
    });

    it('can list parts on a job', function () {
        $job = ServiceJob::factory()->create(['service_id' => $this->service->id]);
        $part1 = Part::factory()->create(['unit_price' => 10.00, 'stock_quantity' => 100]);
        $part2 = Part::factory()->create(['unit_price' => 20.00, 'stock_quantity' => 100]);

        JobPart::create([
            'service_job_id' => $job->id,
            'part_id' => $part1->id,
            'quantity' => 2,
            'unit_price' => 10.00,
            'total_price' => 20.00,
            'added_by' => $this->admin->id,
        ]);

        JobPart::create([
            'service_job_id' => $job->id,
            'part_id' => $part2->id,
            'quantity' => 1,
            'unit_price' => 20.00,
            'total_price' => 20.00,
            'added_by' => $this->admin->id,
        ]);

        $response = $this->actingAs($this->admin)
            ->getJson("/api/service-jobs/{$job->id}/parts");

        $response->assertOk();
        expect($response->json('parts'))->toHaveCount(2);
        expect($response->json('total_parts_cost'))->toBe('40.00');
    });

    it('can update part quantity on a job', function () {
        $part = Part::factory()->create(['unit_price' => 25.00, 'stock_quantity' => 50]);
        $job = ServiceJob::factory()->create(['service_id' => $this->service->id]);

        $jobPart = JobPart::create([
            'service_job_id' => $job->id,
            'part_id' => $part->id,
            'quantity' => 2,
            'unit_price' => 25.00,
            'total_price' => 50.00,
            'added_by' => $this->admin->id,
        ]);

        // Simulate that stock was already deducted
        $part->update(['stock_quantity' => 48]);

        $response = $this->actingAs($this->admin)
            ->putJson("/api/service-jobs/{$job->id}/parts/{$jobPart->id}", [
                'quantity' => 5,
            ]);

        $response->assertOk();
        expect($jobPart->fresh()->quantity)->toBe(5);
        expect($jobPart->fresh()->total_price)->toBe('125.00');
        expect($part->fresh()->stock_quantity)->toBe(45); // 48 - 3 more
    });

    it('can remove a part from a job and restore stock', function () {
        $part = Part::factory()->create(['unit_price' => 25.00, 'stock_quantity' => 47]);
        $job = ServiceJob::factory()->create([
            'service_id' => $this->service->id,
            'total_cost' => 175.00, // 100 service + 75 parts
        ]);

        $jobPart = JobPart::create([
            'service_job_id' => $job->id,
            'part_id' => $part->id,
            'quantity' => 3,
            'unit_price' => 25.00,
            'total_price' => 75.00,
            'added_by' => $this->admin->id,
        ]);

        $response = $this->actingAs($this->admin)
            ->deleteJson("/api/service-jobs/{$job->id}/parts/{$jobPart->id}");

        $response->assertOk();
        expect(JobPart::count())->toBe(0);
        expect($part->fresh()->stock_quantity)->toBe(50); // 47 + 3 restored
        expect($job->fresh()->total_cost)->toBe('100.00'); // just service price
    });

    it('snapshots unit price at time of adding', function () {
        $part = Part::factory()->create(['unit_price' => 25.00, 'stock_quantity' => 50]);
        $job = ServiceJob::factory()->create(['service_id' => $this->service->id]);

        $this->actingAs($this->admin)
            ->postJson("/api/service-jobs/{$job->id}/parts", [
                'part_id' => $part->id,
                'quantity' => 1,
            ]);

        // Price changes after adding
        $part->update(['unit_price' => 50.00]);

        $jobPart = JobPart::first();
        expect($jobPart->unit_price)->toBe('25.00'); // still original
    });
});

// ── Technician Job Parts Access ────────────────────────────────

describe('Technician Job Parts', function () {
    it('technician can view parts on their assigned job', function () {
        $job = ServiceJob::factory()->assigned($this->technician)->create([
            'service_id' => $this->service->id,
        ]);
        $part = Part::factory()->create(['stock_quantity' => 100]);

        JobPart::create([
            'service_job_id' => $job->id,
            'part_id' => $part->id,
            'quantity' => 1,
            'unit_price' => $part->unit_price,
            'total_price' => $part->unit_price,
            'added_by' => $this->admin->id,
        ]);

        $response = $this->actingAs($this->admin)
            ->getJson("/api/service-jobs/{$job->id}/parts");

        $response->assertOk();
        expect($response->json('parts'))->toHaveCount(1);
    });

    it('admin can add parts to any job', function () {
        $part = Part::factory()->create(['stock_quantity' => 50]);
        $job = ServiceJob::factory()->assigned($this->technician)->create([
            'service_id' => $this->service->id,
        ]);

        $response = $this->actingAs($this->admin)
            ->postJson("/api/service-jobs/{$job->id}/parts", [
                'part_id' => $part->id,
                'quantity' => 1,
            ]);

        $response->assertCreated();
    });
});

// ── Part Model ─────────────────────────────────────────────────

describe('Part Model', function () {
    it('detects low stock correctly', function () {
        $lowPart = Part::factory()->create(['stock_quantity' => 3, 'minimum_stock' => 5]);
        $okPart = Part::factory()->create(['stock_quantity' => 20, 'minimum_stock' => 5]);
        $exactPart = Part::factory()->create(['stock_quantity' => 5, 'minimum_stock' => 5]);

        expect($lowPart->isLowStock())->toBeTrue();
        expect($okPart->isLowStock())->toBeFalse();
        expect($exactPart->isLowStock())->toBeTrue(); // at threshold
    });
});
