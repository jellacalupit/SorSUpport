<?php

namespace App\Services;

use App\Models\EmailNotification;
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

    /**
     * Send reminder emails to current handlers for tickets nearing their deadline.
     */
    public function sendDailyReminders(): int
    {
        $tickets = Ticket::query()
            ->whereNotNull('current_handler_id')
            ->whereNotIn('status', [Ticket::STATUS_RESOLVED, Ticket::STATUS_CLOSED, Ticket::STATUS_REJECTED])
            ->whereBetween('deadline', [now(), now()->addDays(3)])
            ->get();

        $sent = 0;

        foreach ($tickets as $ticket) {
            $handler = $ticket->currentHandler;

            if (! $handler?->email) {
                continue;
            }

            $existingReminder = EmailNotification::query()
                ->where('ticket_id', $ticket->id)
                ->where('recipient_email', $handler->email)
                ->where('type', EmailNotification::TYPE_DAILY_REMINDER)
                ->whereIn('status', [
                    EmailNotification::STATUS_PENDING,
                    EmailNotification::STATUS_SENT,
                ])
                ->exists();

            if ($existingReminder) {
                continue;
            }

            $notification = EmailNotification::create([
                'ticket_id' => $ticket->id,
                'recipient_email' => $handler->email,
                'type' => EmailNotification::TYPE_DAILY_REMINDER,
                'status' => EmailNotification::STATUS_PENDING,
            ]);

            if ($this->sendNotification($notification)) {
                $sent++;
            }
        }

        return $sent;
    }

    protected function resolveTemplate(string $type): string
    {
        return match ($type) {
            EmailNotification::TYPE_VERIFICATION => 'emails.notifications.verification',
            EmailNotification::TYPE_SUBMISSION_ACK => 'emails.notifications.submission_ack',
            EmailNotification::TYPE_INVALID_CLOSURE => 'emails.notifications.invalid_closure',
            EmailNotification::TYPE_ASSIGNMENT, EmailNotification::TYPE_RECIPIENT_ASSIGNMENT => 'emails.notifications.assignment',
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
            EmailNotification::TYPE_STATUS_UPDATE, EmailNotification::TYPE_STUDENT_STATUS_UPDATE, EmailNotification::TYPE_ACKNOWLEDGED, EmailNotification::TYPE_RECIPIENT_RESOLVED, EmailNotification::TYPE_RESOLVED => 'Status update',
            EmailNotification::TYPE_ESCALATED => 'Escalation notice',
            EmailNotification::TYPE_DAILY_REMINDER => 'Deadline reminder',
            EmailNotification::TYPE_CLOSED, EmailNotification::TYPE_COMPLAINT_CLOSED => 'Resolution update',
            default => 'SORSUPPORT update',
        };
    }

    protected function resolveBody(string $type, ?Ticket $ticket): string
    {
        $reference = $ticket ? '#'.$ticket->id : 'your ticket';

        return match ($type) {
            EmailNotification::TYPE_VERIFICATION => "Please verify your account to continue using SORSUPPORT for {$reference}.",
            EmailNotification::TYPE_SUBMISSION_ACK => "Your complaint has been received and is being reviewed for {$reference}.",
            EmailNotification::TYPE_INVALID_CLOSURE => "The latest closure request for {$reference} could not be processed automatically.",
            EmailNotification::TYPE_ASSIGNMENT, EmailNotification::TYPE_RECIPIENT_ASSIGNMENT => "A new ticket has been assigned to you for {$reference}.",
            EmailNotification::TYPE_STATUS_UPDATE, EmailNotification::TYPE_STUDENT_STATUS_UPDATE, EmailNotification::TYPE_ACKNOWLEDGED, EmailNotification::TYPE_RECIPIENT_RESOLVED, EmailNotification::TYPE_RESOLVED => "The status for {$reference} has been updated.",
            EmailNotification::TYPE_ESCALATED => "This ticket has been escalated and needs your attention for {$reference}.",
            EmailNotification::TYPE_DAILY_REMINDER => "This is a reminder that {$reference} is approaching its deadline.",
            EmailNotification::TYPE_CLOSED, EmailNotification::TYPE_COMPLAINT_CLOSED => "The ticket {$reference} has been resolved and closed.",
            default => 'An update is available for your ticket.',
        };
    }
}
