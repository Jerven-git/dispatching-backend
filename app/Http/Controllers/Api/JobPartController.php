<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\JobPartResource;
use App\Models\JobPart;
use App\Models\ServiceJob;
use App\Services\InventoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class JobPartController extends Controller
{
    public function __construct(
        private InventoryService $inventoryService,
    ) {}

    /**
     * List parts used on a job.
     */
    public function index(ServiceJob $serviceJob): JsonResponse
    {
        $parts = $serviceJob->parts()
            ->with(['part', 'addedBy:id,name'])
            ->get();

        $totalPartsCost = $parts->sum('total_price');

        return response()->json([
            'parts' => JobPartResource::collection($parts),
            'total_parts_cost' => number_format($totalPartsCost, 2, '.', ''),
        ]);
    }

    /**
     * Add a part to a job.
     */
    public function store(Request $request, ServiceJob $serviceJob): JsonResponse
    {
        $data = $request->validate([
            'part_id' => ['required', 'exists:parts,id'],
            'quantity' => ['required', 'integer', 'min:1'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $jobPart = $this->inventoryService->addPartToJob(
            $serviceJob,
            $data['part_id'],
            $data['quantity'],
            $request->user()->id,
            $data['notes'] ?? null,
        );

        return response()->json([
            'message' => 'Part added to job.',
            'job_part' => new JobPartResource($jobPart),
            'job_total_cost' => $serviceJob->fresh()->total_cost,
        ], 201);
    }

    /**
     * Update quantity of a part on a job.
     */
    public function update(Request $request, ServiceJob $serviceJob, JobPart $part): JsonResponse
    {
        if ($part->service_job_id !== $serviceJob->id) {
            return response()->json(['message' => 'Part does not belong to this job.'], 404);
        }

        $data = $request->validate([
            'quantity' => ['required', 'integer', 'min:1'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $jobPart = $this->inventoryService->updateJobPartQuantity($part, $data['quantity']);

        if (isset($data['notes'])) {
            $jobPart->update(['notes' => $data['notes']]);
        }

        return response()->json([
            'message' => 'Part quantity updated.',
            'job_part' => new JobPartResource($jobPart),
            'job_total_cost' => $serviceJob->fresh()->total_cost,
        ]);
    }

    /**
     * Remove a part from a job.
     */
    public function destroy(ServiceJob $serviceJob, JobPart $part): JsonResponse
    {
        if ($part->service_job_id !== $serviceJob->id) {
            return response()->json(['message' => 'Part does not belong to this job.'], 404);
        }

        $this->inventoryService->removePartFromJob($part);

        return response()->json([
            'message' => 'Part removed from job.',
            'job_total_cost' => $serviceJob->fresh()->total_cost,
        ]);
    }
}
