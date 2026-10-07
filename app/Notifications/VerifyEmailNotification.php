<?php

namespace App\Notifications;

use App\Models\AuditLog;
use Illuminate\Auth\Notifications\VerifyEmail as BaseVerifyEmail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;

class VerifyEmailNotification extends BaseVerifyEmail implements ShouldQueue
{
    use Queueable;

    public function toMail($notifiable): MailMessage
    {
        $verificationUrl = $this->verificationUrl($notifiable);

        AuditLog::create([
            'ticket_id' => null,
            'performed_by' => null,
            'action' => 'email_notification_sent',
            'details' => sprintf('Email sent to %s: "Verify Your SorSUpport Email Address".', $notifiable->email),
        ]);

        return (new MailMessage)
            ->subject('Verify Your SorSUpport Email Address')
            ->view('emails.verify-email', [
                'verificationUrl' => $verificationUrl,
            ]);
    }
}
