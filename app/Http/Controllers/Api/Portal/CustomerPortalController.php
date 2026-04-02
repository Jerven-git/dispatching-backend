<?php

namespace App\Http\Controllers\Api\Portal;

use App\Http\Controllers\Controller;
use App\Http\Resources\InvoiceResource;
use App\Http\Resources\ServiceJobResource;
use App\Models\Invoice;
use App\Models\JobReview;
use App\Models\Service;
use App\Models\ServiceRequest;
use App\Services\ETAService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerPortalController extends Controller
{
    public function __construct(
        private ETAService $etaService,
    ) {}

    // ── My Jobs ──────────────────────────────────────────────────

    public function myJobs(Request $request): JsonResponse
    {
        $customer = $request->user('customer');

        $jobs = $customer->serviceJobs()
            ->with(['service:id,name', 'technician:id,name,phone'])
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->orderByDesc('scheduled_date')
            ->paginate($request->per_page ?? 10);

        return response()->json(ServiceJobResource::collection($jobs)->response()->getData(true));
    }

    public function showJob(Request $request, int $jobId): JsonResponse
    {
        $customer = $request->user('customer');

        $job = $customer->serviceJobs()
            ->with(['service', 'technician:id,name,phone', 'statusLogs'])
            ->findOrFail($jobId);

        return response()->json([
            'job' => new ServiceJobResource($job),
        ]);
    }

    public function jobEta(Request $request, int $jobId): JsonResponse
    {
        $customer = $request->user('customer');
        $job = $customer->serviceJobs()->findOrFail($jobId);

        $eta = $this->etaService->calculateETA($job);

        return response()->json(['eta' => $eta]);
    }

    // ── My Invoices ─────────────────────────────────────────────

    public function myInvoices(Request $request): JsonResponse
    {
        $customer = $request->user('customer');

        $invoices = $customer->invoices()
            ->with(['serviceJob:id,reference_number'])
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->orderByDesc('created_at')
            ->paginate($request->per_page ?? 10);

        return response()->json(InvoiceResource::collection($invoices)->response()->getData(true));
    }

    public function showInvoice(Request $request, int $invoiceId): JsonResponse
    {
        $customer = $request->user('customer');

        $invoice = $customer->invoices()
            ->with(['serviceJob.service', 'serviceJob.technician:id,name'])
            ->findOrFail($invoiceId);

        return response()->json([
            'invoice' => new InvoiceResource($invoice),
        ]);
    }

    public function downloadInvoice(Request $request, int $invoiceId)
    {
        $customer = $request->user('customer');
        $invoice = $customer->invoices()
            ->with(['customer', 'serviceJob.service', 'serviceJob.technician', 'creator'])
            ->findOrFail($invoiceId);

        if (class_exists(\Barryvdh\DomPDF\Facade\Pdf::class)) {
            $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('invoices.pdf', ['invoice' => $invoice]);
            return $pdf->download("invoice-{$invoice->invoice_number}.pdf");
        }

        $html = view('invoices.pdf', ['invoice' => $invoice])->render();
        return response($html)->header('Content-Type', 'text/html');
    }

    // ── Request Service ─────────────────────────────────────────

    public function availableServices(): JsonResponse
    {
        $services = Service::where('is_active', true)
            ->select('id', 'name', 'description', 'base_price', 'estimated_duration_minutes')
            ->orderBy('name')
            ->get();

        return response()->json(['services' => $services]);
    }

    public function submitRequest(Request $request): JsonResponse
    {
        $customer = $request->user('customer');

        $data = $request->validate([
            'service_id' => ['nullable', 'exists:services,id'],
            'description' => ['required', 'string', 'max:2000'],
            'preferred_date' => ['nullable', 'date', 'after_or_equal:today'],
            'preferred_time' => ['nullable', 'date_format:H:i'],
            'address' => ['required', 'string', 'max:500'],
        ]);

        $serviceRequest = ServiceRequest::create([
            'customer_id' => $customer->id,
            ...$data,
        ]);

        $serviceRequest->load('service:id,name');

        return response()->json([
            'message' => 'Service request submitted. We will get back to you shortly.',
            'service_request' => $serviceRequest,
        ], 201);
    }

    public function myRequests(Request $request): JsonResponse
    {
        $customer = $request->user('customer');

        $requests = $customer->serviceRequests()
            ->with('service:id,name')
            ->orderByDesc('created_at')
            ->get();

        return response()->json(['requests' => $requests]);
    }

    // ── Reviews ─────────────────────────────────────────────────

    public function submitReview(Request $request, int $jobId): JsonResponse
    {
        $customer = $request->user('customer');

        $job = $customer->serviceJobs()->where('status', 'completed')->findOrFail($jobId);

        if ($job->reviews ?? JobReview::where('service_job_id', $job->id)->exists()) {
            return response()->json([
                'message' => 'You have already reviewed this job.',
            ], 422);
        }

        $data = $request->validate([
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'comment' => ['nullable', 'string', 'max:2000'],
        ]);

        $review = JobReview::create([
            'service_job_id' => $job->id,
            'customer_id' => $customer->id,
            ...$data,
        ]);

        return response()->json([
            'message' => 'Thank you for your review!',
            'review' => $review,
        ], 201);
    }

    public function myReviews(Request $request): JsonResponse
    {
        $customer = $request->user('customer');

        $reviews = $customer->reviews()
            ->with(['serviceJob:id,reference_number', 'serviceJob.service:id,name'])
            ->orderByDesc('created_at')
            ->get();

        return response()->json(['reviews' => $reviews]);
    }
}
