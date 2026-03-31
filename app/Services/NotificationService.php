<?php

namespace App\Services;

use App\Models\ServiceJob;
use App\Models\User;
use App\Notifications\CustomerJobUpdate;
use App\Notifications\JobAssigned;
use App\Notifications\JobStatusChanged;

class NotificationService
{
    /**
     * Notify technician when they are assigned to a job.
     */
    public function notifyJobAssigned(ServiceJob $job): void
    {
        if (! $job->technician) {
            return;
        }

        $job->loadMissing(['customer', 'service']);
        $job->technician->notify(new JobAssigned($job));
    }

    /**
     * Notify relevant parties when a job status changes.
     */
    public function notifyStatusChanged(ServiceJob $job, string $oldStatus, string $newStatus, User $changedBy): void
    {
        $job->loadMissing(['customer', 'service', 'technician']);

        // Notify admin/dispatchers for important status changes
        if (in_array($newStatus, ['completed', 'cancelled'])) {
            $this->notifyAdminsAndDispatchers($job, $oldStatus, $newStatus, $changedBy);
        }

        // Notify customer for customer-facing status changes
        if (in_array($newStatus, ['on_the_way', 'completed']) && $job->customer->email) {
            $job->customer->notify(new CustomerJobUpdate($job, $newStatus));
        }

        // Notify assigned technician if dispatcher/admin changes their job status
        if ($job->technician && $job->technician->id !== $changedBy->id) {
            $job->technician->notify(new JobStatusChanged($job, $oldStatus, $newStatus, $changedBy->name));
        }
    }

    /**
     * Notify all admins and dispatchers about a status change.
     */
    private function notifyAdminsAndDispatchers(ServiceJob $job, string $oldStatus, string $newStatus, User $changedBy): void
    {
        $users = User::whereIn('role', ['admin', 'dispatcher'])
            ->where('is_active', true)
            ->where('id', '!=', $changedBy->id) // Don't notify the person who made the change
            ->get();

        foreach ($users as $user) {
            $user->notify(new JobStatusChanged($job, $oldStatus, $newStatus, $changedBy->name));
        }
    }
}
