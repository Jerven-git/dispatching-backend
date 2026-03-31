<?php

namespace App\Notifications;

use App\Models\ServiceJob;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CustomerJobUpdate extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private ServiceJob $job,
        private string $eventType,
    ) {}

    public function via(object $notifiable): array
    {
        // Customer notifications are email-only (no database — customers aren't system users)
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $customer = $this->job->customer;
        $technician = $this->job->technician;
        $service = $this->job->service;

        return match ($this->eventType) {
            'on_the_way' => (new MailMessage)
                ->subject("Your technician is on the way!")
                ->greeting("Hi {$customer->name},")
                ->line("Your technician **{$technician->name}** is on their way for your **{$service->name}** appointment.")
                ->line("**Address:** {$this->job->address}")
                ->line("If you need to reach the technician, call: {$technician->phone}"),

            'completed' => (new MailMessage)
                ->subject("Your service has been completed")
                ->greeting("Hi {$customer->name},")
                ->line("Your **{$service->name}** job has been completed.")
                ->line("**Reference:** {$this->job->reference_number}")
                ->line($this->job->total_cost ? "**Total:** $" . number_format((float) $this->job->total_cost, 2) : '')
                ->line("Thank you for choosing our services!"),

            default => (new MailMessage)
                ->subject("Update on your service - {$this->job->reference_number}")
                ->greeting("Hi {$customer->name},")
                ->line("There is an update on your **{$service->name}** service.")
                ->line("**Status:** " . ucfirst(str_replace('_', ' ', $this->eventType))),
        };
    }

    public function toArray(object $notifiable): array
    {
        return [
            'job_id' => $this->job->id,
            'reference_number' => $this->job->reference_number,
            'event_type' => $this->eventType,
        ];
    }
}
