<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreServiceJobRequest;
use App\Http\Requests\UpdateJobStatusRequest;
use App\Http\Requests\UpdateServiceJobRequest;
use App\Http\Resources\ServiceJobResource;
use App\Models\ServiceJob;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ServiceJobController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = ServiceJob::with(['customer', 'service', 'technician', 'creator']);

        // Technicians only see their own assigned jobs
        if ($request->user()->isTechnician()) {
            $query->where('technician_id', $request->user()->id);
        }

        $query->when($request->status, fn ($q, $status) => $q->where('status', $status))
            ->when($request->priority, fn ($q, $priority) => $q->where('priority', $priority))
            ->when($request->technician_id, fn ($q, $id) => $q->where('technician_id', $id))
            ->when($request->date_from, fn ($q, $date) => $q->where('scheduled_date', '>=', $date))
            ->when($request->date_to, fn ($q, $date) => $q->where('scheduled_date', '<=', $date))
            ->when($request->search, fn ($q, $search) => $q->where('reference_number', 'like', "%{$search}%")
                ->orWhereHas('customer', fn ($cq) => $cq->where('name', 'like', "%{$search}%"))
            );

        $jobs = $query->orderByDesc('scheduled_date')
            ->paginate($request->per_page ?? 15);

        return response()->json(ServiceJobResource::collection($jobs)->response()->getData(true));
    }

    public function store(StoreServiceJobRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['created_by'] = $request->user()->id;

        if (! empty($data['technician_id'])) {
            $data['status'] = 'assigned';
        }

        $job = ServiceJob::create($data);
        $job->load(['customer', 'service', 'technician', 'creator']);

        return response()->json([
            'message' => 'Job created successfully.',
            'job' => new ServiceJobResource($job),
        ], 201);
    }

    public function show(ServiceJob $serviceJob): JsonResponse
    {
        $serviceJob->load(['customer', 'service', 'technician', 'creator']);

        return response()->json([
            'job' => new ServiceJobResource($serviceJob),
        ]);
    }

    public function update(UpdateServiceJobRequest $request, ServiceJob $serviceJob): JsonResponse
    {
        $data = $request->validated();

        // Auto-set status to assigned when technician is assigned
        if (isset($data['technician_id']) && $data['technician_id'] && $serviceJob->status === 'pending') {
            $data['status'] = 'assigned';
        }

        $serviceJob->update($data);
        $serviceJob->load(['customer', 'service', 'technician', 'creator']);

        return response()->json([
            'message' => 'Job updated successfully.',
            'job' => new ServiceJobResource($serviceJob),
        ]);
    }

    public function updateStatus(UpdateJobStatusRequest $request, ServiceJob $serviceJob): JsonResponse
    {
        $data = $request->validated();

        if ($data['status'] === 'in_progress' && ! $serviceJob->started_at) {
            $data['started_at'] = now();
        }

        if ($data['status'] === 'completed') {
            $data['completed_at'] = now();
        }

        $serviceJob->update($data);
        $serviceJob->load(['customer', 'service', 'technician', 'creator']);

        return response()->json([
            'message' => 'Job status updated successfully.',
            'job' => new ServiceJobResource($serviceJob),
        ]);
    }

    public function destroy(ServiceJob $serviceJob): JsonResponse
    {
        $serviceJob->delete();

        return response()->json([
            'message' => 'Job deleted successfully.',
        ]);
    }
}
