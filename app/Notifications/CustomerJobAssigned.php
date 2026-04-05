<?php

namespace App\Notifications;

use App\Models\ServiceJob;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CustomerJobAssigned extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private ServiceJob $job,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $customer = $this->job->customer;
        $technician = $this->job->technician;
        $service = $this->job->service;

        return (new MailMessage)
            ->subject("Technician assigned to your {$service->name} job")
            ->greeting("Hi {$customer->name},")
            ->line("A technician has been assigned to your **{$service->name}** service request.")
            ->line("**Reference:** {$this->job->reference_number}")
            ->line("**Technician:** {$technician->name}")
            ->line("**Scheduled:** {$this->job->scheduled_date}" . ($this->job->scheduled_time ? " at {$this->job->scheduled_time}" : ''))
            ->line("**Address:** {$this->job->address}")
            ->line("We will notify you when the technician is on their way.");
    }

    public function toArray(object $notifiable): array
    {
        return [
            'job_id' => $this->job->id,
            'reference_number' => $this->job->reference_number,
        ];
    }
}
