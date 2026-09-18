<?php

namespace App\Notifications;

use App\Models\AuditLog;
use Illuminate\Auth\Notifications\ResetPassword as BaseResetPassword;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;

class ResetPasswordNotification extends BaseResetPassword
{
    use Queueable;

    public function toMail($notifiable): MailMessage
    {
        $resetUrl = url(route('password.reset', [
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ], false));

        AuditLog::create([
            'ticket_id' => null,
            'performed_by' => null,
            'action' => 'email_notification_sent',
            'details' => sprintf(
                'Email sent to %s. Subject: "Reset Your SorSUpport Password". Message: A password reset link was sent. Notification type: password_reset.',
                $notifiable->email
            ),
        ]);

        return (new MailMessage)
            ->subject('Reset Your SorSUpport Password')
            ->view('emails.password-reset', [
                'resetUrl' => $resetUrl,
                'expires' => config('auth.passwords.'.config('auth.defaults.passwords').'.expire', 15),
            ]);
    }
}
