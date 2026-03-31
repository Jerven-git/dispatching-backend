<?php

namespace App\Listeners;

use App\Events\TechnicianAssigned;
use App\Services\NotificationService;

class SendJobAssignedNotification
{
    public function __construct(
        private NotificationService $notificationService,
    ) {}

    public function handle(TechnicianAssigned $event): void
    {
        $this->notificationService->notifyJobAssigned($event->job);
    }
}
