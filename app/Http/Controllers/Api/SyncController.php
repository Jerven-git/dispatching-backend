<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\SyncRequest;
use App\Models\JobChecklistEntry;
use App\Models\JobComment;
use App\Models\ServiceJob;
use App\Services\LocationTrackingService;
use App\Services\StatusLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;

class SyncController extends Controller
{
    public function __construct(
        private LocationTrackingService $locationService,
        private StatusLogService $statusLogService,
    ) {}

    /**
     * Process a batch of queued actions from an offline technician.
     */
    public function sync(SyncRequest $request): JsonResponse
    {
        $user = $request->user();
        $actions = $request->validated('actions');
        $lastSyncedAt = Carbon::parse($request->validated('last_synced_at'));

        $results = [];
        $errors = [];

        // Sort actions by timestamp to process in order
        usort($actions, fn ($a, $b) => Carbon::parse($a['timestamp'])->timestamp - Carbon::parse($b['timestamp'])->timestamp);

        foreach ($actions as $index => $action) {
            try {
                $result = match ($action['type']) {
                    'status_update' => $this->processStatusUpdate($user, $action),
                    'location_update' => $this->processLocationUpdate($user, $action),
                    'checklist_toggle' => $this->processChecklistToggle($user, $action),
                    'comment' => $this->processComment($user, $action),
                };
                $results[] = ['index' => $index, 'type' => $action['type'], 'status' => 'ok', ...$result];
            } catch (\Exception $e) {
                $errors[] = ['index' => $index, 'type' => $action['type'], 'status' => 'error', 'message' => $e->getMessage()];
            }
        }

        // Gather updates since last sync for the technician's jobs
        $updatedJobs = ServiceJob::where('technician_id', $user->id)
            ->where('updated_at', '>', $lastSyncedAt)
            ->with(['customer:id,name', 'service:id,name'])
            ->get(['id', 'reference_number', 'status', 'technician_notes', 'updated_at', 'customer_id', 'service_id']);

        return response()->json([
            'processed' => count($results),
            'errors' => $errors,
            'results' => $results,
            'updated_jobs' => $updatedJobs,
            'synced_at' => now()->toIso8601String(),
        ]);
    }

    private function processStatusUpdate($user, array $action): array
    {
        $job = ServiceJob::where('id', $action['job_id'])
            ->where('technician_id', $user->id)
            ->firstOrFail();

        $oldStatus = $job->status;
        $this->statusLogService->transition(
            $job,
            $action['status'],
            $user->id,
            $action['technician_notes'] ?? null,
        );

        if (! empty($action['technician_notes'])) {
            $job->update(['technician_notes' => $action['technician_notes']]);
        }

        return ['job_id' => $job->id, 'old_status' => $oldStatus, 'new_status' => $action['status']];
    }

    private function processLocationUpdate($user, array $action): array
    {
        $location = $this->locationService->recordLocation($user->id, [
            'latitude' => $action['latitude'],
            'longitude' => $action['longitude'],
            'accuracy' => $action['accuracy'] ?? null,
            'recorded_at' => $action['timestamp'],
        ]);

        return ['location_id' => $location->id];
    }

    private function processChecklistToggle($user, array $action): array
    {
        $job = ServiceJob::where('id', $action['job_id'])
            ->where('technician_id', $user->id)
            ->firstOrFail();

        $entry = JobChecklistEntry::where('service_job_id', $job->id)
            ->where('checklist_item_id', $action['checklist_item_id'])
            ->first();

        if ($entry) {
            $entry->update([
                'is_completed' => ! $entry->is_completed,
                'completed_by' => $entry->is_completed ? null : $user->id,
                'completed_at' => $entry->is_completed ? null : now(),
            ]);
        }

        return ['job_id' => $job->id, 'checklist_item_id' => $action['checklist_item_id']];
    }

    private function processComment($user, array $action): array
    {
        $job = ServiceJob::where('id', $action['job_id'])
            ->where('technician_id', $user->id)
            ->firstOrFail();

        $comment = JobComment::create([
            'service_job_id' => $job->id,
            'user_id' => $user->id,
            'body' => $action['body'],
            'is_internal' => false,
        ]);

        return ['job_id' => $job->id, 'comment_id' => $comment->id];
    }
}
