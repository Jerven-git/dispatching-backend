<?php

namespace App\Notifications;

use App\Models\ServiceRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CustomerServiceRequestUpdate extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private ServiceRequest $serviceRequest,
        private string $status,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $customer = $this->serviceRequest->customer;
        $service = $this->serviceRequest->service;

        if ($this->status === 'approved') {
            return (new MailMessage)
                ->subject("Your service request has been approved")
                ->greeting("Hi {$customer->name},")
                ->line("Your request for **{$service->name}** has been approved.")
                ->line("We will schedule your service and assign a technician shortly.")
                ->line($this->serviceRequest->admin_notes ? "**Note:** {$this->serviceRequest->admin_notes}" : '');
        }

        return (new MailMessage)
            ->subject("Update on your service request")
            ->greeting("Hi {$customer->name},")
            ->line("Unfortunately, your request for **{$service->name}** could not be accommodated at this time.")
            ->line($this->serviceRequest->admin_notes ? "**Reason:** {$this->serviceRequest->admin_notes}" : '')
            ->line("Please feel free to submit a new request or contact us for alternatives.");
    }

    public function toArray(object $notifiable): array
    {
        return [
            'service_request_id' => $this->serviceRequest->id,
            'status' => $this->status,
        ];
    }
}
