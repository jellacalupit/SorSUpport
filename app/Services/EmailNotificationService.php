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
            $subject = $this->resolveSubject($notification->type);
            $body = $this->resolveBody($notification->type, $notification->ticket);

            Mail::send($template, ['subject' => $subject, 'body' => $body, 'ticket' => $notification->ticket], function (Message $message) use ($notification, $subject): void {
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

    protected function resolveSubject(string $type): string
    {
        return match ($type) {
            EmailNotification::TYPE_VERIFICATION => 'Verification required',
            EmailNotification::TYPE_SUBMISSION_ACK => 'Complaint received',
            EmailNotification::TYPE_INVALID_CLOSURE => 'Closure update',
            EmailNotification::TYPE_ASSIGNMENT, EmailNotification::TYPE_RECIPIENT_ASSIGNMENT => 'Ticket assigned',
            EmailNotification::TYPE_INFORMATIONAL_FORWARD => 'Informational ticket forwarded',
            EmailNotification::TYPE_STATUS_UPDATE, EmailNotification::TYPE_STUDENT_STATUS_UPDATE, EmailNotification::TYPE_ACKNOWLEDGED, EmailNotification::TYPE_RECIPIENT_RESOLVED, EmailNotification::TYPE_RESOLVED => 'Status update',
            EmailNotification::TYPE_ESCALATED => 'Escalation notice',
            EmailNotification::TYPE_DAILY_REMINDER => 'Deadline reminder',
            EmailNotification::TYPE_CLOSED, EmailNotification::TYPE_COMPLAINT_CLOSED => 'Resolution update',
            EmailNotification::TYPE_MESSAGE_POSTED => 'New ticket message',
            EmailNotification::TYPE_CLARIFICATION_REQUESTED => 'More details needed for your ticket',
            EmailNotification::TYPE_CLARIFICATION_PROVIDED => 'Student replied with more details',
            EmailNotification::TYPE_REFERRED => 'Ticket referred to a committee',
            EmailNotification::TYPE_FURTHER_ACTION_REQUESTED => 'Further action requested',
            EmailNotification::TYPE_RESOLUTION_ACCEPTED => 'Resolution accepted',
            EmailNotification::TYPE_WITHDRAWN => 'Ticket withdrawn',
            default => 'SORSUPPORT update',
        };
    }

    protected function resolveBody(string $type, ?Ticket $ticket): string
    {
        $reference = $ticket?->complaint?->reference_number ?? ($ticket ? '#'.$ticket->id : 'your ticket');
        $status = $ticket?->status_label;

        return match ($type) {
            EmailNotification::TYPE_VERIFICATION => "Please verify your account to continue using SORSUPPORT for {$reference}.",
            EmailNotification::TYPE_SUBMISSION_ACK => "Your complaint has been received and is being reviewed for {$reference}.",
            EmailNotification::TYPE_INVALID_CLOSURE => "After review, the SDS Office closed {$reference} because it could not be acted on. Open the ticket in SORSUPPORT to read the reason.",
            EmailNotification::TYPE_ASSIGNMENT, EmailNotification::TYPE_RECIPIENT_ASSIGNMENT => "A new ticket has been assigned to you for {$reference}.",
            EmailNotification::TYPE_INFORMATIONAL_FORWARD => "An informational ticket has been forwarded to you for {$reference}.",
            EmailNotification::TYPE_STATUS_UPDATE, EmailNotification::TYPE_STUDENT_STATUS_UPDATE, EmailNotification::TYPE_ACKNOWLEDGED, EmailNotification::TYPE_RECIPIENT_RESOLVED, EmailNotification::TYPE_RESOLVED => $status
                ? "The status of {$reference} is now {$status}. Open the ticket in SORSUPPORT for the details."
                : "The status for {$reference} has been updated.",
            EmailNotification::TYPE_ESCALATED => "This ticket has been escalated and needs your attention for {$reference}.",
            EmailNotification::TYPE_DAILY_REMINDER => "This is a reminder that {$reference} is approaching its deadline.",
            EmailNotification::TYPE_CLOSED, EmailNotification::TYPE_COMPLAINT_CLOSED => "The ticket {$reference} has been closed. Open it in SORSUPPORT to see the reason.",
            EmailNotification::TYPE_MESSAGE_POSTED => "A new message was posted on {$reference}.",
            EmailNotification::TYPE_CLARIFICATION_REQUESTED => "The SDS Office needs more details before it can review {$reference}. Open the ticket in SORSUPPORT and reply in the conversation.",
            EmailNotification::TYPE_CLARIFICATION_PROVIDED => "The student replied with more details on {$reference}. It is ready for review again.",
            EmailNotification::TYPE_REFERRED => "The ticket {$reference} has been referred to " . ($ticket?->referred_to ?: 'a committee') . '. You will be notified when there is an outcome.',
            EmailNotification::TYPE_FURTHER_ACTION_REQUESTED => "The student is asking for further action on {$reference}. It is back in progress.",
            EmailNotification::TYPE_RESOLUTION_ACCEPTED => "The student accepted the resolution of {$reference}. The ticket is now closed.",
            EmailNotification::TYPE_WITHDRAWN => "The student withdrew {$reference}. The ticket is now closed.",
            default => 'An update is available for your ticket.',
        };
    }

    /**
     * Record the exact email basis visible in the admin audit trail.
     */
    protected function recordDeliveryInAuditTrail(EmailNotification $notification, string $subject, string $body): void
    {
        $details = sprintf(
            'Email sent to %s. Subject: "%s". Message: %s Notification type: %s.',
            $notification->recipient_email,
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
