<?php

namespace App\Listeners;

use App\Events\JobStatusChanged;
use App\Services\NotificationService;

class SendJobStatusNotifications
{
    public function __construct(
        private NotificationService $notificationService,
    ) {}

    public function handle(JobStatusChanged $event): void
    {
        $this->notificationService->notifyStatusChanged(
            $event->job,
            $event->oldStatus,
            $event->newStatus,
            $event->changedBy,
        );
    }
}
