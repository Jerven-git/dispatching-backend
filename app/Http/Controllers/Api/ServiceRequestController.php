<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ServiceJob;
use App\Models\ServiceRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ServiceRequestController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $requests = ServiceRequest::with(['customer:id,name,phone', 'service:id,name'])
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->orderByDesc('created_at')
            ->paginate($request->per_page ?? 15);

        return response()->json([
            'data' => $requests->items(),
            'meta' => [
                'current_page' => $requests->currentPage(),
                'last_page' => $requests->lastPage(),
                'per_page' => $requests->perPage(),
                'total' => $requests->total(),
            ],
        ]);
    }

    public function approve(Request $request, ServiceRequest $serviceRequest): JsonResponse
    {
        if ($serviceRequest->status !== 'pending') {
            return response()->json(['message' => 'Only pending requests can be approved.'], 422);
        }

        $data = $request->validate([
            'technician_id' => ['nullable', 'exists:users,id'],
            'scheduled_date' => ['required', 'date', 'after_or_equal:today'],
            'scheduled_time' => ['nullable', 'date_format:H:i'],
            'admin_notes' => ['nullable', 'string', 'max:2000'],
        ]);

        // Create a job from the request
        $job = ServiceJob::create([
            'customer_id' => $serviceRequest->customer_id,
            'service_id' => $serviceRequest->service_id,
            'technician_id' => $data['technician_id'] ?? null,
            'created_by' => $request->user()->id,
            'status' => ! empty($data['technician_id']) ? 'assigned' : 'pending',
            'address' => $serviceRequest->address,
            'description' => $serviceRequest->description,
            'scheduled_date' => $data['scheduled_date'],
            'scheduled_time' => $data['scheduled_time'] ?? null,
        ]);

        $serviceRequest->update([
            'status' => 'approved',
            'converted_job_id' => $job->id,
            'admin_notes' => $data['admin_notes'] ?? null,
        ]);

        return response()->json([
            'message' => 'Request approved and job created.',
            'job_id' => $job->id,
            'reference_number' => $job->reference_number,
        ]);
    }

    public function decline(Request $request, ServiceRequest $serviceRequest): JsonResponse
    {
        if ($serviceRequest->status !== 'pending') {
            return response()->json(['message' => 'Only pending requests can be declined.'], 422);
        }

        $data = $request->validate([
            'admin_notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $serviceRequest->update([
            'status' => 'declined',
            'admin_notes' => $data['admin_notes'] ?? null,
        ]);

        return response()->json(['message' => 'Request declined.']);
    }
}
