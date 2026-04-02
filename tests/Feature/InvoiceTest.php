<?php

use App\Models\Invoice;
use App\Models\ServiceJob;
use App\Models\User;

beforeEach(function () {
    $this->admin = User::factory()->create(['role' => 'admin']);
    $this->dispatcher = User::factory()->create(['role' => 'dispatcher']);
    $this->technician = User::factory()->create(['role' => 'technician']);
});

describe('Generate Invoice', function () {
    it('generates an invoice from a completed job', function () {
        $job = ServiceJob::factory()->completed($this->technician)->create([
            'total_cost' => 250.00,
        ]);

        $response = $this->actingAs($this->admin)
            ->postJson("/api/service-jobs/{$job->id}/invoice", [
                'tax_rate' => 10,
                'notes' => 'Thanks for your business',
                'due_days' => 15,
            ]);

        $response->assertCreated();
        expect($response->json('invoice.subtotal'))->toEqual('250.00');
        expect($response->json('invoice.tax_rate'))->toEqual('10.00');
        expect($response->json('invoice.tax_amount'))->toEqual('25.00');
        expect($response->json('invoice.total'))->toEqual('275.00');
        expect($response->json('invoice.status'))->toBe('draft');
        expect($response->json('invoice.invoice_number'))->toStartWith('INV-');
    });

    it('rejects invoice for non-completed job', function () {
        $job = ServiceJob::factory()->assigned($this->technician)->create();

        $response = $this->actingAs($this->admin)
            ->postJson("/api/service-jobs/{$job->id}/invoice");

        $response->assertUnprocessable();
    });

    it('generates invoice with zero tax by default', function () {
        $job = ServiceJob::factory()->completed($this->technician)->create([
            'total_cost' => 100.00,
        ]);

        $response = $this->actingAs($this->admin)
            ->postJson("/api/service-jobs/{$job->id}/invoice");

        $response->assertCreated();
        expect($response->json('invoice.tax_amount'))->toEqual('0.00');
        expect($response->json('invoice.total'))->toEqual('100.00');
    });

    it('blocks technician from generating invoices', function () {
        $job = ServiceJob::factory()->completed($this->technician)->create();

        $response = $this->actingAs($this->technician)
            ->postJson("/api/service-jobs/{$job->id}/invoice");

        $response->assertForbidden();
    });
});

describe('Invoice CRUD', function () {
    it('lists invoices with filters', function () {
        Invoice::factory()->count(3)->create(['created_by' => $this->admin->id]);
        Invoice::factory()->paid()->count(2)->create(['created_by' => $this->admin->id]);

        $response = $this->actingAs($this->admin)
            ->getJson('/api/invoices');

        $response->assertOk();
        expect($response->json('data'))->toHaveCount(5);

        // Filter by status
        $response = $this->actingAs($this->admin)
            ->getJson('/api/invoices?status=paid');

        $response->assertOk();
        expect($response->json('data'))->toHaveCount(2);
    });

    it('shows invoice detail', function () {
        $invoice = Invoice::factory()->create(['created_by' => $this->admin->id]);

        $response = $this->actingAs($this->admin)
            ->getJson("/api/invoices/{$invoice->id}");

        $response->assertOk();
        expect($response->json('invoice.invoice_number'))->toBe($invoice->invoice_number);
    });

    it('updates invoice details', function () {
        $invoice = Invoice::factory()->create([
            'created_by' => $this->admin->id,
            'subtotal' => 200.00,
            'tax_rate' => 0,
            'tax_amount' => 0,
            'total' => 200.00,
        ]);

        $response = $this->actingAs($this->admin)
            ->putJson("/api/invoices/{$invoice->id}", [
                'tax_rate' => 8,
                'notes' => 'Updated note',
            ]);

        $response->assertOk();
        expect($response->json('invoice.tax_rate'))->toEqual('8.00');
        expect($response->json('invoice.tax_amount'))->toEqual('16.00');
        expect($response->json('invoice.total'))->toEqual('216.00');
    });

    it('marks invoice as paid', function () {
        $invoice = Invoice::factory()->create([
            'created_by' => $this->admin->id,
            'status' => 'sent',
        ]);

        $response = $this->actingAs($this->admin)
            ->patchJson("/api/invoices/{$invoice->id}/paid");

        $response->assertOk();
        expect($invoice->fresh()->status)->toBe('paid');
        expect($invoice->fresh()->paid_at)->not->toBeNull();
    });

    it('marks invoice as sent', function () {
        $invoice = Invoice::factory()->create([
            'created_by' => $this->admin->id,
            'status' => 'draft',
        ]);

        $response = $this->actingAs($this->admin)
            ->patchJson("/api/invoices/{$invoice->id}/sent");

        $response->assertOk();
        expect($invoice->fresh()->status)->toBe('sent');
    });

    it('soft deletes an invoice', function () {
        $invoice = Invoice::factory()->create(['created_by' => $this->admin->id]);

        $response = $this->actingAs($this->admin)
            ->deleteJson("/api/invoices/{$invoice->id}");

        $response->assertOk();
        expect(Invoice::find($invoice->id))->toBeNull();
        expect(Invoice::withTrashed()->find($invoice->id))->not->toBeNull();
    });
});

describe('Invoice PDF', function () {
    it('downloads invoice as PDF', function () {
        $invoice = Invoice::factory()->create(['created_by' => $this->admin->id]);

        $response = $this->actingAs($this->admin)
            ->get("/api/invoices/{$invoice->id}/pdf");

        $response->assertOk();
        $contentType = $response->headers->get('Content-Type');
        // Accepts either PDF (dompdf installed) or HTML (fallback)
        expect($contentType)->toMatch('/application\/pdf|text\/html/');
    });
});

describe('Invoice Number Sequence', function () {
    it('auto-generates sequential invoice numbers', function () {
        $job1 = ServiceJob::factory()->completed($this->technician)->create(['total_cost' => 100]);
        $job2 = ServiceJob::factory()->completed($this->technician)->create(['total_cost' => 200]);

        $this->actingAs($this->admin)->postJson("/api/service-jobs/{$job1->id}/invoice");
        $this->actingAs($this->admin)->postJson("/api/service-jobs/{$job2->id}/invoice");

        $invoices = Invoice::orderBy('id')->get();
        $year = now()->format('Y');
        expect($invoices[0]->invoice_number)->toBe("INV-{$year}-00001");
        expect($invoices[1]->invoice_number)->toBe("INV-{$year}-00002");
    });
});
