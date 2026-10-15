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
     * Send all pending notifications that are due, including ones waiting to be tried again.
     */
    public function sendPendingNotifications(): int
    {
        $notifications = EmailNotification::query()
            ->where('status', EmailNotification::STATUS_PENDING)
            ->where(fn ($due) => $due->whereNull('next_attempt_at')->orWhere('next_attempt_at', '<=', now()))
            ->orderBy('id')
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

                // Reminders about waiting tickets are marked important, so mail apps that sort
                // or alert by priority put them first.
                if ($notification->type === EmailNotification::TYPE_WAITING_REMINDER) {
                    $message->priority(1);
                    $message->getSymfonyMessage()->getHeaders()->addTextHeader('Importance', 'High');
                }
            });

            $notification->update([
                'status' => EmailNotification::STATUS_SENT,
                'attempts' => $notification->attempts + 1,
                'next_attempt_at' => null,
                'sent_at' => now(),
            ]);

            $this->recordDeliveryInAuditTrail($notification, $subject, $body);

            return true;
        } catch (\Throwable $e) {
            Log::error('Email notification failed', [
                'notification_id' => $notification->id,
                'type' => $notification->type,
                'recipient' => $notification->recipient_email,
                'attempt' => $notification->attempts + 1,
                'exception' => $e->getMessage(),
            ]);

            $this->recordFailedAttempt($notification);

            return false;
        }
    }

    /**
     * Try the email again later, waiting longer each time. After the last try it is given up
     * on, and the audit trail says so, so the SDS Office can reach the person another way.
     */
    protected function recordFailedAttempt(EmailNotification $notification): void
    {
        $attempts = $notification->attempts + 1;

        if ($attempts < EmailNotification::MAX_ATTEMPTS) {
            $delays = EmailNotification::RETRY_DELAYS_MINUTES;

            $notification->update([
                'attempts' => $attempts,
                'next_attempt_at' => now()->addMinutes($delays[min($attempts, count($delays)) - 1]),
            ]);

            return;
        }

        $notification->update([
            'status' => EmailNotification::STATUS_FAILED,
            'attempts' => $attempts,
            'next_attempt_at' => null,
            'sent_at' => now(),
        ]);

        AuditLog::create([
            'ticket_id' => $notification->ticket_id,
            'performed_by' => null,
            'action' => 'email_notification_failed',
            'details' => sprintf('Email to %s could not be sent after %d tries. Please reach them another way.', $this->recipientForAuditTrail($notification), $attempts),
        ]);
    }

    /**
     * The address to show in the audit trail. A student who hid their identity is not named.
     */
    protected function recipientForAuditTrail(EmailNotification $notification): string
    {
        $complaint = $notification->ticket?->complaint;

        return $complaint?->is_anonymous && $notification->recipient_email === $complaint->student?->user?->email
            ? 'the student'
            : $notification->recipient_email;
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
            EmailNotification::TYPE_DAILY_REMINDER, EmailNotification::TYPE_WAITING_REMINDER => 'emails.notifications.daily_reminder',
            EmailNotification::TYPE_CLOSED, EmailNotification::TYPE_COMPLAINT_CLOSED => 'emails.notifications.closure_resolved',
            default => 'emails.notifications.status_update',
        };
    }

    /**
     * Record the exact email basis visible in the admin audit trail.
     */
    protected function recordDeliveryInAuditTrail(EmailNotification $notification, string $subject, string $body): void
    {
        // Who it went to and what it was about; the full wording is in the email itself.
        $details = sprintf('Email sent to %s: "%s".', $this->recipientForAuditTrail($notification), $subject);

        AuditLog::create([
            'ticket_id' => $notification->ticket_id,
            'performed_by' => null,
            'action' => 'email_notification_sent',
            'details' => $details,
        ]);
    }
}
