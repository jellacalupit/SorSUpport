<?php

namespace App\Services;

use App\Models\EmailNotification;
use App\Models\Ticket;
use App\Models\User;

/**
 * Writes each ticket email for its purpose and its reader. The same event reads differently for
 * the student, the office handling the ticket and the SDS Office, and never tells a handler who
 * a hidden-identity student is or what a sensitive ticket is about.
 */
class TicketEmailComposer
{
    public const AUDIENCE_STUDENT = 'student';

    public const AUDIENCE_RECIPIENT = 'recipient';

    public const AUDIENCE_ADMIN = 'admin';

    /**
     * @return array{subject: string, heading: string, lines: list<string>, details: array<string, string>, actionUrl: ?string, actionLabel: string, body: string}
     */
    public function compose(EmailNotification $notification): array
    {
        $ticket = $notification->ticket;
        $reader = User::query()->where('email', $notification->recipient_email)->first();
        $audience = $this->audience($reader, $ticket);
        $reference = $ticket?->complaint?->reference_number ?? ($ticket ? 'Ticket #' . $ticket->id : 'your ticket');

        [$subject, $heading, $lines] = $this->message($notification->type, $audience, $ticket, $reference);

        return [
            'subject' => $subject,
            'heading' => $heading,
            'lines' => $lines,
            'details' => $ticket ? $this->details($ticket, $audience, $notification->type) : [],
            'actionUrl' => $ticket ? $this->ticketUrl($ticket, $audience) : null,
            'actionLabel' => 'Open Ticket',
            'body' => implode(' ', $lines),
        ];
    }

    protected function audience(?User $reader, ?Ticket $ticket): string
    {
        if ($reader?->isSdsAdmin()) {
            return self::AUDIENCE_ADMIN;
        }

        if ($reader?->isRecipient()) {
            return self::AUDIENCE_RECIPIENT;
        }

        return self::AUDIENCE_STUDENT;
    }

    /**
     * @return array{0: string, 1: string, 2: list<string>} subject, heading, paragraphs
     */
    protected function message(string $type, string $audience, ?Ticket $ticket, string $reference): array
    {
        $isStudent = $audience === self::AUDIENCE_STUDENT;
        $handler = $ticket ? $this->handlerName($ticket) : 'the assigned office';
        $days = Ticket::FURTHER_ACTION_DAYS;

        return match ($type) {
            EmailNotification::TYPE_SUBMISSION_ACK => [
                "We received your ticket {$reference}",
                'Your ticket was submitted',
                array_values(array_filter([
                    'Thank you for raising your concern. The Student Development Services (SDS) Office will review it and send it to the office that can act on it.',
                    $ticket?->complaint?->is_anonymous ? 'You chose to hide your identity. Your name and student ID are not shown to anyone handling the ticket.' : null,
                    'You will receive an email at every step, and you can follow the ticket in SorSUpport at any time.',
                ])),
            ],

            EmailNotification::TYPE_ASSIGNMENT => [
                "New ticket for review: {$reference}",
                'A new ticket is waiting for review',
                ['A student submitted a new ticket. Please review it, ask for more details if needed, and assign it to the office that should handle it.'],
            ],

            EmailNotification::TYPE_RECIPIENT_ASSIGNMENT => [
                "Ticket {$reference} was assigned to you",
                'A ticket was assigned to you',
                [
                    'The SDS Office assigned this ticket to you for handling.',
                    'Please open the ticket and acknowledge it, so the student knows their concern is being attended to. You can talk with the student in the ticket\'s conversation.',
                ],
            ],

            EmailNotification::TYPE_INFORMATIONAL_FORWARD => [
                "For your information: {$reference}",
                'A concern was shared with your office',
                ['The SDS Office forwarded this concern to your office for your information. It is a record only. No reply or action is needed.'],
            ],

            EmailNotification::TYPE_CLARIFICATION_REQUESTED => [
                "More details needed for your ticket {$reference}",
                'The SDS Office needs more details',
                [
                    'Before your ticket can be reviewed, the SDS Office needs you to clarify something.',
                    'Open the ticket, read their message in the conversation, and reply there. Your ticket continues as soon as you answer.',
                ],
            ],

            EmailNotification::TYPE_CLARIFICATION_PROVIDED => [
                "The student replied on {$reference}",
                'The student sent the details you asked for',
                ['The student replied to your request for more details. The ticket is back in the review list and ready to be assigned.'],
            ],

            EmailNotification::TYPE_ESCALATED => match ($audience) {
                self::AUDIENCE_STUDENT => [
                    "Your ticket {$reference} was escalated",
                    'Your ticket was escalated',
                    ["The SDS Office moved your ticket to a higher level so it can be acted on. It is now with {$handler}."],
                ],
                self::AUDIENCE_RECIPIENT => [
                    "Ticket {$reference} was escalated to you",
                    'A ticket was escalated to you',
                    ['The SDS Office escalated this ticket to you because it needs attention at your level. Please open it and continue from the conversation so far.'],
                ],
                default => [
                    "Ticket {$reference} was escalated",
                    'The ticket was escalated',
                    ["The ticket is now with {$handler}. The date of escalation is recorded on the ticket."],
                ],
            },

            EmailNotification::TYPE_REFERRED => [
                $isStudent ? "Your ticket {$reference} was referred" : "Ticket {$reference} was referred",
                $isStudent ? 'Your ticket was referred to a committee' : 'The ticket was referred to a committee',
                [
                    'The ticket was referred to ' . ($ticket?->referred_to ?: 'a committee') . ', which decides cases of this kind outside SorSUpport.',
                    $isStudent
                        ? 'This can take time. The SDS Office will record the outcome on your ticket and you will be notified when it does.'
                        : 'The SDS Office will record the outcome on the ticket once it is decided.',
                ],
            ],

            EmailNotification::TYPE_RECIPIENT_RESOLVED => [
                "Ticket {$reference} was marked resolved",
                'A handler marked the ticket resolved',
                ["{$handler} marked the ticket as resolved. The student has {$days} days to accept the resolution or request further action. You can also confirm and close it, or send it back as not yet resolved."],
            ],

            EmailNotification::TYPE_FURTHER_ACTION_REQUESTED => [
                "Further action requested on {$reference}",
                'The student asked for further action',
                ['The student is not satisfied with the resolution and asked for further action. The ticket is in progress again. Please read their reason in the conversation.'],
            ],

            EmailNotification::TYPE_RESOLUTION_ACCEPTED => [
                "Resolution accepted for {$reference}",
                'The student accepted the resolution',
                ['The student accepted the resolution, so the ticket is now closed. No further action is needed.'],
            ],

            EmailNotification::TYPE_WITHDRAWN => [
                "Ticket {$reference} was withdrawn",
                'The student withdrew the ticket',
                ['The student withdrew this ticket, so it is now closed. No further action is needed.'],
            ],

            EmailNotification::TYPE_INVALID_CLOSURE => [
                "Your ticket {$reference} was closed",
                'Your ticket could not be acted on',
                [
                    'After reviewing your ticket, the SDS Office closed it because it could not be acted on. The reason is shown below.',
                    'If you believe this is a mistake, you may submit a new ticket with more details or visit the SDS Office.',
                ],
            ],

            EmailNotification::TYPE_CLOSED, EmailNotification::TYPE_COMPLAINT_CLOSED => [
                "Your ticket {$reference} is closed",
                'Your ticket is closed',
                array_values(array_filter([
                    'Your ticket has been closed by the SDS Office.',
                    $ticket?->canBeRated() ? 'Please take a moment to rate how your concern was handled. Your feedback helps the university improve.' : null,
                ])),
            ],

            EmailNotification::TYPE_MESSAGE_POSTED => [
                "New message on ticket {$reference}",
                'You have a new message',
                ['Someone replied in the conversation of this ticket. Open the ticket to read the message and answer.'],
            ],

            EmailNotification::TYPE_STUDENT_STATUS_UPDATE => $this->studentStatusMessage($ticket, $reference, $handler),

            EmailNotification::TYPE_STATUS_UPDATE, EmailNotification::TYPE_ACKNOWLEDGED, EmailNotification::TYPE_RESOLVED => [
                "Ticket {$reference} is now " . ($ticket?->status_label ?? 'updated'),
                'The ticket status changed',
                ['The status of a ticket you handle has changed. Open the ticket to see what happened.'],
            ],

            EmailNotification::TYPE_DAILY_REMINDER => [
                "Reminder for ticket {$reference}",
                'A ticket is waiting for action',
                ['This ticket has been waiting for action. Please open it and continue.'],
            ],

            default => [
                "Update on {$reference}",
                'There is an update on a ticket',
                ['Open the ticket in SorSUpport to see the update.'],
            ],
        };
    }

    /**
     * What a status change means for the student, in words they can act on.
     *
     * @return array{0: string, 1: string, 2: list<string>}
     */
    protected function studentStatusMessage(?Ticket $ticket, string $reference, string $handler): array
    {
        $days = Ticket::FURTHER_ACTION_DAYS;
        $deadline = $ticket?->furtherActionDeadline()?->copy()->setTimezone('Asia/Manila')->format('F j, Y');

        return match ($ticket?->status) {
            Ticket::STATUS_ASSIGNED => [
                "Your ticket {$reference} was assigned",
                'Your ticket was assigned',
                ["The SDS Office reviewed your ticket and assigned it to {$handler}. You will be notified when they start working on it."],
            ],
            Ticket::STATUS_IN_PROGRESS => [
                "Your ticket {$reference} is in progress",
                'Your ticket is being handled',
                ["{$handler} is now working on your ticket. You can message them in the ticket's conversation if you have something to add."],
            ],
            Ticket::STATUS_RESOLVED => [
                "Your ticket {$reference} was resolved",
                'Your ticket was resolved',
                [
                    'A resolution was recorded on your ticket. Please open it and read the resolution in the conversation.',
                    'If you are satisfied, accept the resolution to close the ticket. If the concern is not settled, you may request further action' . ($deadline ? " until {$deadline}" : " within {$days} days") . '.',
                ],
            ],
            default => [
                "Update on your ticket {$reference}",
                'Your ticket was updated',
                ['The status of your ticket is now ' . ($ticket?->status_label ?? 'updated') . '. Open the ticket to see the details.'],
            ],
        };
    }

    /**
     * The facts shown under the message. Handlers never get a sensitive subject by email.
     *
     * @return array<string, string>
     */
    protected function details(Ticket $ticket, string $audience, string $type): array
    {
        $complaint = $ticket->complaint;
        $isStudent = $audience === self::AUDIENCE_STUDENT;

        $details = [
            'Ticket' => (string) ($complaint?->reference_number ?? '#' . $ticket->id),
            'Subject' => (string) ($isStudent ? ($complaint?->subject_title ?: 'Untitled') : ($complaint?->public_subject ?? 'Untitled')),
            'Category' => (string) ($complaint?->category?->name ?? 'Uncategorized'),
            'Status' => $ticket->status_label,
        ];

        if ($ticket->isActive()) {
            $details['Handled by'] = $this->handlerName($ticket);
        }

        if ($ticket->status === Ticket::STATUS_REFERRED && $ticket->referred_to) {
            $details['Referred to'] = $ticket->referred_to;
        }

        if (in_array($ticket->status, [Ticket::STATUS_RESOLVED, Ticket::STATUS_CLOSED], true) && $ticket->resolution_label) {
            $details['Resolution'] = $ticket->resolution_label;
        }

        if ($ticket->status === Ticket::STATUS_CLOSED && $ticket->closure_label) {
            $details['Reason for closing'] = $ticket->closure_label;
        }

        // Only the student is told the explanation by email; staff read it on the ticket.
        if ($isStudent && $ticket->status === Ticket::STATUS_CLOSED && filled($ticket->closure_reason) && $ticket->closure_type !== Ticket::CLOSURE_WITHDRAWN) {
            $details['Explanation'] = (string) $ticket->closure_reason;
        }

        return $details;
    }

    protected function handlerName(Ticket $ticket): string
    {
        $handler = $ticket->currentHandler ?? $ticket->assignee;

        if (! $handler) {
            return 'the SDS Office';
        }

        if ($handler->isSdsAdmin()) {
            return 'the SDS Office';
        }

        $office = $handler->recipient?->unit;

        return $office ? "{$handler->display_name} ({$office})" : $handler->display_name;
    }

    protected function ticketUrl(Ticket $ticket, string $audience): ?string
    {
        if (! $ticket->complaint) {
            return null;
        }

        return match ($audience) {
            self::AUDIENCE_ADMIN => route('admin.complaints.show', $ticket->complaint),
            self::AUDIENCE_RECIPIENT => route('recipient.complaints.show', $ticket->complaint),
            default => route('student.complaints.show', $ticket->complaint),
        };
    }
}
