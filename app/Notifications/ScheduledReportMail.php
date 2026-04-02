<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ScheduledReportMail extends Notification
{
    use Queueable;

    public function __construct(
        private string $reportName,
        private string $reportType,
        private array $reportData,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject("Scheduled Report: {$this->reportName}")
            ->greeting("Report: {$this->reportName}")
            ->line("Report type: {$this->formatType($this->reportType)}")
            ->line("Generated at: " . now()->format('Y-m-d H:i'));

        // Add summary data to email body
        foreach ($this->reportData as $key => $value) {
            if (is_numeric($value)) {
                $label = ucwords(str_replace('_', ' ', $key));
                $mail->line("{$label}: {$value}");
            }
        }

        $mail->line('Log in to the system for the full detailed report.');

        return $mail;
    }

    private function formatType(string $type): string
    {
        return ucwords(str_replace('_', ' ', $type));
    }
}
