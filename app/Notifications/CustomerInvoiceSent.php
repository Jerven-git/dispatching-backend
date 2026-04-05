<?php

namespace App\Notifications;

use App\Models\Invoice;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CustomerInvoiceSent extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private Invoice $invoice,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $customer = $this->invoice->customer;

        return (new MailMessage)
            ->subject("Invoice {$this->invoice->invoice_number} from your service provider")
            ->greeting("Hi {$customer->name},")
            ->line("You have a new invoice for a recent service.")
            ->line("**Invoice:** {$this->invoice->invoice_number}")
            ->line("**Total:** $" . number_format((float) $this->invoice->total, 2))
            ->line("**Due Date:** {$this->invoice->due_date}")
            ->line('You can view and download this invoice from your customer portal.');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'invoice_id' => $this->invoice->id,
            'invoice_number' => $this->invoice->invoice_number,
        ];
    }
}
