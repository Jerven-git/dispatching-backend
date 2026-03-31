<?php

namespace App\Notifications;

use App\Models\ServiceJob;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class JobAssigned extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private ServiceJob $job,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("New Job Assigned: {$this->job->reference_number}")
            ->greeting("Hi {$notifiable->name},")
            ->line("You have been assigned a new job.")
            ->line("**Reference:** {$this->job->reference_number}")
            ->line("**Service:** {$this->job->service->name}")
            ->line("**Customer:** {$this->job->customer->name}")
            ->line("**Address:** {$this->job->address}")
            ->line("**Scheduled:** {$this->job->scheduled_date->format('M d, Y')}" . ($this->job->scheduled_time ? " at {$this->job->scheduled_time}" : ''))
            ->line("**Priority:** " . ucfirst($this->job->priority));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'New Job Assigned',
            'message' => "You have been assigned to {$this->job->reference_number} - {$this->job->service->name} at {$this->job->address}",
            'job_id' => $this->job->id,
            'reference_number' => $this->job->reference_number,
            'type' => 'job_assigned',
        ];
    }
}
