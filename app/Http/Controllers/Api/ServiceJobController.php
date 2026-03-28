<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\AssignTechnicianRequest;
use App\Http\Requests\StoreServiceJobRequest;
use App\Http\Requests\UpdateJobStatusRequest;
use App\Http\Requests\UpdateServiceJobRequest;
use App\Http\Resources\ServiceJobResource;
use App\Models\ServiceJob;
use App\Services\JobAssignmentService;
use App\Services\StatusLogService;
use App\Services\StatusTransitionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ServiceJobController extends Controller
{
    public function __construct(
        private JobAssignmentService $assignmentService,
        private StatusLogService $statusLogService,
        private StatusTransitionService $transitionService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $query = ServiceJob::with(['customer', 'service', 'technician', 'creator']);

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

        $this->statusLogService->log(
            $job,
            null,
            $job->status,
            $request->user()->id,
            'Job created',
        );

        $job->load(['customer', 'service', 'technician', 'creator']);

        return response()->json([
            'message' => 'Job created successfully.',
            'job' => new ServiceJobResource($job),
        ], 201);
    }

    public function show(ServiceJob $serviceJob): JsonResponse
    {
        $serviceJob->load([
            'customer',
            'service',
            'technician',
            'creator',
            'statusLogs.changedByUser',
        ]);

        return response()->json([
            'job' => new ServiceJobResource($serviceJob),
        ]);
    }

    public function update(UpdateServiceJobRequest $request, ServiceJob $serviceJob): JsonResponse
    {
        $oldStatus = $serviceJob->status;
        $validated = $request->validated();

        if (isset($validated['status']) && $validated['status'] !== $oldStatus) {
            $this->transitionService->validate($oldStatus, $validated['status']);
        }

        $serviceJob->update($validated);

        if ($serviceJob->status !== $oldStatus) {
            $this->statusLogService->log(
                $serviceJob,
                $oldStatus,
                $serviceJob->status,
                $request->user()->id,
            );
        }

        $serviceJob->load(['customer', 'service', 'technician', 'creator']);

        return response()->json([
            'message' => 'Job updated successfully.',
            'job' => new ServiceJobResource($serviceJob),
        ]);
    }

    public function updateStatus(UpdateJobStatusRequest $request, ServiceJob $serviceJob): JsonResponse
    {
        $this->statusLogService->transition(
            $serviceJob,
            $request->validated('status'),
            $request->user()->id,
            $request->validated('technician_notes'),
        );

        // Update technician_notes on the job if provided
        if ($request->validated('technician_notes')) {
            $serviceJob->update(['technician_notes' => $request->validated('technician_notes')]);
        }

        $serviceJob->load(['customer', 'service', 'technician', 'creator']);

        return response()->json([
            'message' => 'Job status updated successfully.',
            'job' => new ServiceJobResource($serviceJob),
        ]);
    }

    public function myJobs(Request $request): JsonResponse
    {
        $query = ServiceJob::with(['customer', 'service', 'technician', 'creator'])
            ->where('technician_id', $request->user()->id);

        $query->when($request->status, fn ($q, $status) => $q->where('status', $status));

        $jobs = $query->orderByDesc('scheduled_date')
            ->paginate($request->per_page ?? 15);

        return response()->json(ServiceJobResource::collection($jobs)->response()->getData(true));
    }

    public function updateMyJobStatus(UpdateJobStatusRequest $request, ServiceJob $serviceJob): JsonResponse
    {
        if ($serviceJob->technician_id !== $request->user()->id) {
            return response()->json(['message' => 'This job is not assigned to you.'], 403);
        }

        $this->statusLogService->transition(
            $serviceJob,
            $request->validated('status'),
            $request->user()->id,
            $request->validated('technician_notes'),
        );

        if ($request->validated('technician_notes')) {
            $serviceJob->update(['technician_notes' => $request->validated('technician_notes')]);
        }

        $serviceJob->load(['customer', 'service', 'technician', 'creator']);

        return response()->json([
            'message' => 'Job status updated successfully.',
            'job' => new ServiceJobResource($serviceJob),
        ]);
    }

    public function assignTechnician(AssignTechnicianRequest $request, ServiceJob $serviceJob): JsonResponse
    {
        $oldStatus = $serviceJob->status;

        $job = $this->assignmentService->assign(
            $serviceJob,
            $request->validated('technician_id'),
        );

        if ($job->status !== $oldStatus) {
            $this->statusLogService->log(
                $job,
                $oldStatus,
                $job->status,
                $request->user()->id,
                $job->technician_id
                    ? "Technician assigned: {$job->technician->name}"
                    : 'Technician unassigned',
            );
        }

        return response()->json([
            'message' => $job->technician_id
                ? 'Technician assigned successfully.'
                : 'Technician unassigned successfully.',
            'job' => new ServiceJobResource($job),
        ]);
    }

    public function technicianWorkloads(): JsonResponse
    {
        $workloads = $this->assignmentService->getTechnicianWorkloads();

        return response()->json([
            'technicians' => $workloads,
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
