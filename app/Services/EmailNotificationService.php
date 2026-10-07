<?php

namespace App\Services;

use App\Models\EmailNotification;
use App\Models\AuditLog;
use App\Models\Ticket;
use Illuminate\Mail\Message;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class EmailNotificationService
{
    /**
     * Send all pending notifications and update their status.
     */
    public function sendPendingNotifications(): int
    {
        $notifications = EmailNotification::query()
            ->where('status', EmailNotification::STATUS_PENDING)
            ->get();

        $sent = 0;

        foreach ($notifications as $notification) {
            if ($this->sendNotification($notification)) {
                $sent++;
            }
        }

        return $sent;
    }

    /**
     * Send a single notification using the correct template.
     */
    public function sendNotification(EmailNotification $notification): bool
    {
        if ($notification->status !== EmailNotification::STATUS_PENDING) {
            return false;
        }

        if (blank($notification->recipient_email)) {
            $notification->update([
                'status' => EmailNotification::STATUS_FAILED,
                'sent_at' => now(),
            ]);

            return false;
        }

        try {
            $template = $this->resolveTemplate($notification->type);
            $email = app(TicketEmailComposer::class)->compose($notification);
            $subject = $email['subject'];
            $body = $email['body'];

            Mail::send($template, $email + ['ticket' => $notification->ticket], function (Message $message) use ($notification, $subject): void {
                $message->to($notification->recipient_email)
                    ->subject($subject);
            });

            $notification->update([
                'status' => EmailNotification::STATUS_SENT,
                'sent_at' => now(),
            ]);

            $this->recordDeliveryInAuditTrail($notification, $subject, $body);

            return true;
        } catch (\Throwable $e) {
            Log::error('Email notification failed', [
                'notification_id' => $notification->id,
                'type' => $notification->type,
                'recipient' => $notification->recipient_email,
                'exception' => $e->getMessage(),
            ]);

            $notification->update([
                'status' => EmailNotification::STATUS_FAILED,
                'sent_at' => now(),
            ]);

            return false;
        }
    }

    protected function resolveTemplate(string $type): string
    {
        return match ($type) {
            EmailNotification::TYPE_VERIFICATION => 'emails.notifications.verification',
            EmailNotification::TYPE_SUBMISSION_ACK => 'emails.notifications.submission_ack',
            EmailNotification::TYPE_INVALID_CLOSURE => 'emails.notifications.invalid_closure',
            EmailNotification::TYPE_ASSIGNMENT, EmailNotification::TYPE_RECIPIENT_ASSIGNMENT, EmailNotification::TYPE_INFORMATIONAL_FORWARD => 'emails.notifications.assignment',
            EmailNotification::TYPE_STATUS_UPDATE, EmailNotification::TYPE_STUDENT_STATUS_UPDATE, EmailNotification::TYPE_ACKNOWLEDGED, EmailNotification::TYPE_RECIPIENT_RESOLVED, EmailNotification::TYPE_RESOLVED => 'emails.notifications.status_update',
            EmailNotification::TYPE_ESCALATED => 'emails.notifications.escalation',
            EmailNotification::TYPE_DAILY_REMINDER => 'emails.notifications.daily_reminder',
            EmailNotification::TYPE_CLOSED, EmailNotification::TYPE_COMPLAINT_CLOSED => 'emails.notifications.closure_resolved',
            default => 'emails.notifications.status_update',
        };
    }

    /**
     * Record the exact email basis visible in the admin audit trail.
     */
    protected function recordDeliveryInAuditTrail(EmailNotification $notification, string $subject, string $body): void
    {
        // The audit trail must not reveal the address of a student who hid their identity.
        $complaint = $notification->ticket?->complaint;
        $recipient = $complaint?->is_anonymous && $notification->recipient_email === $complaint->student?->user?->email
            ? 'the student'
            : $notification->recipient_email;

        $details = sprintf(
            'Email sent to %s. Subject: "%s". Message: %s Notification type: %s.',
            $recipient,
            $subject,
            $body,
            $notification->type
        );

        AuditLog::create([
            'ticket_id' => $notification->ticket_id,
            'performed_by' => null,
            'action' => 'email_notification_sent',
            'details' => $details,
        ]);
    }
}
