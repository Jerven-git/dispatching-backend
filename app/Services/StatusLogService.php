<?php

namespace App\Services;

use App\Events\JobStatusChanged;
use App\Models\JobStatusLog;
use App\Models\ServiceJob;
use App\Models\User;

class StatusLogService
{
    public function __construct(
        private StatusTransitionService $transitionService,
    ) {}

    /**
     * Transition a job to a new status, set timestamps, and log the change.
     */
    public function transition(
        ServiceJob $job,
        string $newStatus,
        int $changedBy,
        ?string $remarks = null,
    ): ServiceJob {
        $oldStatus = $job->status;

        if ($oldStatus === $newStatus) {
            return $job;
        }

        $this->transitionService->validate($oldStatus, $newStatus);

        $data = ['status' => $newStatus];

        if ($newStatus === 'in_progress' && ! $job->started_at) {
            $data['started_at'] = now();
        }

        if ($newStatus === 'completed') {
            $data['completed_at'] = now();
        }

        if ($newStatus === 'cancelled') {
            $data['cancelled_at'] = now();
        }

        $job->update($data);

        $this->log($job, $oldStatus, $newStatus, $changedBy, $remarks);

        $changedByUser = User::find($changedBy);
        if ($changedByUser) {
            JobStatusChanged::dispatch($job, $oldStatus, $newStatus, $changedByUser);
        }

        return $job;
    }

    /**
     * Log a status change without modifying the job (for cases where the
     * job was already updated, like initial creation or assignment).
     */
    public function log(
        ServiceJob $job,
        ?string $oldStatus,
        string $newStatus,
        int $changedBy,
        ?string $remarks = null,
    ): JobStatusLog {
        return JobStatusLog::create([
            'service_job_id' => $job->id,
            'old_status' => $oldStatus,
            'new_status' => $newStatus,
            'changed_by' => $changedBy,
            'remarks' => $remarks,
        ]);
    }
}
