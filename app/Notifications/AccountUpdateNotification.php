<?php

namespace App\Notifications;

use App\Models\AuditLog;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AccountUpdateNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly string $subject,
        private readonly string $message,
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        AuditLog::create([
            'ticket_id' => null,
            'performed_by' => null,
            'action' => 'email_notification_sent',
            'details' => sprintf('Email sent to %s: "%s".', $notifiable->email, $this->subject),
        ]);

        return (new MailMessage)
            ->subject($this->subject)
            ->view('emails.account-update', [
                'subject' => $this->subject,
                'email_message' => $this->message,
            ]);
    }
}
