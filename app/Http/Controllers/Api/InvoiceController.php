<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\InvoiceResource;
use App\Models\Invoice;
use App\Models\ServiceJob;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class InvoiceController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Invoice::with(['customer:id,name', 'serviceJob:id,reference_number'])
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->when($request->customer_id, fn ($q, $id) => $q->where('customer_id', $id))
            ->when($request->search, fn ($q, $s) => $q->where('invoice_number', 'like', "%{$s}%")
                ->orWhereHas('customer', fn ($cq) => $cq->where('name', 'like', "%{$s}%"))
            )
            ->orderByDesc('created_at')
            ->paginate($request->per_page ?? 15);

        return response()->json(InvoiceResource::collection($query)->response()->getData(true));
    }

    public function show(Invoice $invoice): JsonResponse
    {
        $invoice->load(['customer', 'serviceJob.service', 'serviceJob.technician', 'creator']);

        return response()->json([
            'invoice' => new InvoiceResource($invoice),
        ]);
    }

    public function generate(Request $request, ServiceJob $serviceJob): JsonResponse
    {
        $request->validate([
            'tax_rate' => ['sometimes', 'numeric', 'min:0', 'max:100'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'due_days' => ['sometimes', 'integer', 'min:1', 'max:365'],
        ]);

        if ($serviceJob->status !== 'completed') {
            return response()->json([
                'message' => 'Invoices can only be generated for completed jobs.',
            ], 422);
        }

        $subtotal = (float) ($serviceJob->total_cost ?? 0);
        $taxRate = (float) $request->input('tax_rate', 0);
        $taxAmount = round($subtotal * ($taxRate / 100), 2);
        $total = round($subtotal + $taxAmount, 2);
        $dueDays = (int) $request->input('due_days', 30);

        $invoice = Invoice::create([
            'service_job_id' => $serviceJob->id,
            'customer_id' => $serviceJob->customer_id,
            'created_by' => $request->user()->id,
            'subtotal' => $subtotal,
            'tax_rate' => $taxRate,
            'tax_amount' => $taxAmount,
            'total' => $total,
            'status' => 'draft',
            'notes' => $request->input('notes'),
            'issued_date' => now()->toDateString(),
            'due_date' => now()->addDays($dueDays)->toDateString(),
        ]);

        $invoice->load(['customer', 'serviceJob.service', 'serviceJob.technician', 'creator']);

        return response()->json([
            'message' => 'Invoice generated.',
            'invoice' => new InvoiceResource($invoice),
        ], 201);
    }

    public function update(Request $request, Invoice $invoice): JsonResponse
    {
        $data = $request->validate([
            'tax_rate' => ['sometimes', 'numeric', 'min:0', 'max:100'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'due_date' => ['sometimes', 'date'],
            'status' => ['sometimes', 'in:draft,sent,paid,overdue,cancelled'],
        ]);

        // Recalculate if tax_rate changed
        if (isset($data['tax_rate'])) {
            $subtotal = (float) $invoice->subtotal;
            $data['tax_amount'] = round($subtotal * ((float) $data['tax_rate'] / 100), 2);
            $data['total'] = round($subtotal + $data['tax_amount'], 2);
        }

        if (isset($data['status']) && $data['status'] === 'paid' && ! $invoice->paid_at) {
            $data['paid_at'] = now();
        }

        $invoice->update($data);
        $invoice->load(['customer', 'serviceJob.service', 'creator']);

        return response()->json([
            'message' => 'Invoice updated.',
            'invoice' => new InvoiceResource($invoice),
        ]);
    }

    public function markAsPaid(Invoice $invoice): JsonResponse
    {
        $invoice->update([
            'status' => 'paid',
            'paid_at' => now(),
        ]);

        return response()->json([
            'message' => 'Invoice marked as paid.',
            'invoice' => new InvoiceResource($invoice),
        ]);
    }

    public function markAsSent(Invoice $invoice): JsonResponse
    {
        $invoice->update(['status' => 'sent']);

        // Send email notification to customer
        app(\App\Services\NotificationService::class)->notifyInvoiceSent($invoice);

        return response()->json([
            'message' => 'Invoice marked as sent.',
            'invoice' => new InvoiceResource($invoice),
        ]);
    }

    public function downloadPdf(Invoice $invoice): Response|JsonResponse
    {
        $invoice->load(['customer', 'serviceJob.service', 'serviceJob.technician', 'creator']);

        if (! class_exists(\Barryvdh\DomPDF\Facade\Pdf::class)) {
            // Fallback: return HTML if dompdf not installed
            $html = view('invoices.pdf', ['invoice' => $invoice])->render();
            return response($html)->header('Content-Type', 'text/html');
        }

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('invoices.pdf', ['invoice' => $invoice]);

        return $pdf->download("invoice-{$invoice->invoice_number}.pdf");
    }

    public function destroy(Invoice $invoice): JsonResponse
    {
        $invoice->delete();

        return response()->json(['message' => 'Invoice deleted.']);
    }
}
