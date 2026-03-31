<?php

namespace App\Notifications;

use App\Models\ServiceJob;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class JobStatusChanged extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private ServiceJob $job,
        private string $oldStatus,
        private string $newStatus,
        private string $changedByName,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $statusLabel = ucfirst(str_replace('_', ' ', $this->newStatus));

        return (new MailMessage)
            ->subject("Job {$this->job->reference_number} - {$statusLabel}")
            ->greeting("Hi {$notifiable->name},")
            ->line("A job status has been updated.")
            ->line("**Reference:** {$this->job->reference_number}")
            ->line("**Status:** " . ucfirst(str_replace('_', ' ', $this->oldStatus)) . " → {$statusLabel}")
            ->line("**Updated by:** {$this->changedByName}")
            ->line("**Customer:** {$this->job->customer->name}")
            ->line("**Service:** {$this->job->service->name}");
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Job ' . ucfirst(str_replace('_', ' ', $this->newStatus)),
            'message' => "{$this->job->reference_number} has been marked as " . str_replace('_', ' ', $this->newStatus) . " by {$this->changedByName}",
            'job_id' => $this->job->id,
            'reference_number' => $this->job->reference_number,
            'old_status' => $this->oldStatus,
            'new_status' => $this->newStatus,
            'type' => 'job_status_changed',
        ];
    }
}
