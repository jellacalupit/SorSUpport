<?php

namespace Tests\Feature;

use App\Models\Complaint;
use App\Models\ComplaintCategory;
use App\Models\AuditLog;
use App\Models\EmailNotification;
use App\Models\Recipient;
use App\Models\Student;
use App\Models\Ticket;
use App\Models\User;
use App\Services\TicketWorkflow;
use App\Services\TicketEmailComposer;
use App\Services\TicketUnreadService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TicketWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected User $student;

    protected User $otherStudent;

    protected User $handler;

    protected User $otherRecipient;

    protected ComplaintCategory $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = $this->user(User::ROLE_SDS_ADMIN, 'Ada Admin');
        $this->student = $this->user(User::ROLE_STUDENT, 'Sam Student');
        $this->otherStudent = $this->user(User::ROLE_STUDENT, 'Olive Other');
        $this->handler = $this->user(User::ROLE_RECIPIENT, 'Rey Registrar');
        $this->otherRecipient = $this->user(User::ROLE_RECIPIENT, 'Dana Dean');

        foreach ([$this->student, $this->otherStudent] as $index => $user) {
            Student::create([
                'user_id' => $user->id,
                'student_id' => '2024000' . $index,
                'college' => 'CICT',
                'program' => 'BSIT',
                'year_level' => '1',
                'block' => '1',
            ]);
        }

        foreach ([$this->handler, $this->otherRecipient] as $index => $user) {
            Recipient::create(['user_id' => $user->id, 'staff_id' => 'STAFF-' . $index, 'unit' => 'Registrar', 'designation' => 'Staff']);
        }

        $this->category = ComplaintCategory::create(['name' => 'Student Services', 'is_active' => true]);
    }

    protected function user(string $role, string $name): User
    {
        [$first, $last] = explode(' ', $name);

        return User::factory()->create([
            'role' => $role,
            'name' => $name,
            'first_name' => $first,
            'last_name' => $last,
            'is_active' => true,
            'email_verified_at' => now(),
            'must_change_password' => false,
        ]);
    }

    /**
     * A ticket in the given status, handled by the recipient once it has left review.
     */
    protected function ticket(string $status, array $attributes = []): Ticket
    {
        $complaint = Complaint::create([
            'reference_number' => Complaint::generateReferenceNumber(),
            'student_id' => $this->student->student->id,
            'category_id' => $this->category->id,
            'subject_title' => 'Transcript request',
            'description' => 'My transcript has not been released.',
            'is_anonymous' => false,
            'status' => $status,
        ]);

        $underReview = in_array($status, [Ticket::STATUS_SUBMITTED, Ticket::STATUS_NEEDS_CLARIFICATION], true);

        return Ticket::create($attributes + [
            'complaint_id' => $complaint->id,
            'status' => $status,
            'classification' => $underReview ? null : Ticket::CLASSIFICATION_NEEDS_RESOLUTION,
            'jurisdiction' => $underReview ? null : Ticket::JURISDICTION_RECIPIENT,
            'assigned_to' => $underReview ? null : $this->handler->id,
            'current_handler_id' => $underReview ? $this->admin->id : $this->handler->id,
            'resolved_at' => $status === Ticket::STATUS_RESOLVED ? now() : null,
            'referred_to' => $status === Ticket::STATUS_REFERRED ? 'Student Disciplinary Board' : null,
            'clarification_requested_at' => $status === Ticket::STATUS_NEEDS_CLARIFICATION ? now() : null,
        ]);
    }

    // ------------------------------------------------------------------ rules

    public function test_the_statuses_are_the_handbook_based_set_without_rejected(): void
    {
        $this->assertSame(
            ['Submitted', 'Needs Clarification', 'Assigned', 'In Progress', 'Escalated', 'Referred', 'Resolved', 'Closed'],
            array_values(Ticket::STATUS_LABELS)
        );
        $this->assertArrayNotHasKey('rejected', Ticket::STATUS_LABELS);
        $this->assertArrayNotHasKey('pending', Ticket::STATUS_LABELS);
    }

    public function test_every_status_change_outside_the_allowed_map_is_refused(): void
    {
        foreach (array_keys(Ticket::STATUS_LABELS) as $from) {
            foreach (array_keys(Ticket::STATUS_LABELS) as $to) {
                if ($from === $to) {
                    continue;
                }

                $ticket = $this->ticket($from);
                $allowed = in_array($to, Ticket::TRANSITIONS[$from], true);

                try {
                    $ticket->update(['status' => $to]);
                    $this->assertTrue($allowed, "{$from} -> {$to} should have been refused.");
                    $this->assertSame($to, $ticket->fresh()->status);
                } catch (\DomainException) {
                    $this->assertFalse($allowed, "{$from} -> {$to} should have been allowed.");
                    $this->assertSame($from, $ticket->fresh()->status);
                }
            }
        }
    }

    public function test_a_closed_ticket_cannot_change_status(): void
    {
        $this->assertSame([], Ticket::TRANSITIONS[Ticket::STATUS_CLOSED]);
    }

    public function test_each_action_is_only_available_to_the_right_person_from_the_right_status(): void
    {
        $workflow = app(TicketWorkflow::class);
        $people = [
            TicketWorkflow::BY_ADMIN => $this->admin,
            TicketWorkflow::BY_HANDLER => $this->handler,
            TicketWorkflow::BY_STUDENT => $this->student,
            'other recipient' => $this->otherRecipient,
            'other student' => $this->otherStudent,
        ];

        foreach (array_keys(Ticket::STATUS_LABELS) as $status) {
            $ticket = $this->ticket($status);

            foreach (TicketWorkflow::RULES as $action => $rule) {
                foreach ($people as $who => $user) {
                    $expected = $who === $rule['by'] && in_array($status, $rule['from'], true);

                    // A ticket under review is held by the admin, so no recipient is its handler yet.
                    if ($who === TicketWorkflow::BY_HANDLER && $ticket->isAwaitingReview()) {
                        $expected = false;
                    }

                    $this->assertSame(
                        $expected,
                        $workflow->can($user, $ticket, $action),
                        sprintf('%s / %s / %s', $status, $action, $who)
                    );
                }
            }
        }
    }

    public function test_no_recipient_action_leads_to_closed(): void
    {
        foreach (TicketWorkflow::RULES as $action => $rule) {
            if ($rule['by'] === TicketWorkflow::BY_HANDLER) {
                $this->assertNotSame(Ticket::STATUS_CLOSED, $rule['to'], "{$action} must not close a ticket.");
            }
        }
    }

    // ------------------------------------------------------------------ clarification

    public function test_admin_asks_for_details_and_the_student_reply_returns_the_ticket_for_review(): void
    {
        $ticket = $this->ticket(Ticket::STATUS_SUBMITTED);

        $this->actingAs($this->admin)
            ->post(route('admin.tickets.clarification', $ticket), ['clarification_message' => 'Which semester is this about?'])
            ->assertSessionHasNoErrors();

        $ticket->refresh();
        $this->assertSame(Ticket::STATUS_NEEDS_CLARIFICATION, $ticket->status);
        $this->assertSame(Ticket::STATUS_NEEDS_CLARIFICATION, $ticket->complaint->status);
        $this->assertTrue($ticket->thread->is_active);
        $this->assertDatabaseHas('thread_messages', ['content' => 'Which semester is this about?', 'sender_id' => $this->admin->id]);
        $this->assertDatabaseHas('audit_logs', ['ticket_id' => $ticket->id, 'action' => 'clarification_requested']);
        $this->assertDatabaseHas('email_notifications', ['ticket_id' => $ticket->id, 'type' => 'clarification_requested', 'recipient_email' => $this->student->email]);

        $this->actingAs($this->student)
            ->get(route('student.complaints.show', $ticket->complaint))
            ->assertOk()
            ->assertSee('In review')
            ->assertSee('The SDS Office needs more details')
            ->assertSee('Which semester is this about?');

        $this->actingAs($this->student)
            ->post(route('student.complaints.reply', $ticket->complaint), ['content' => 'First semester, 2025-2026.'])
            ->assertSessionHasNoErrors();

        $ticket->refresh();
        $this->assertSame(Ticket::STATUS_SUBMITTED, $ticket->status);
        $this->assertFalse($ticket->thread->is_active);
        $this->assertDatabaseHas('audit_logs', ['ticket_id' => $ticket->id, 'action' => 'clarification_provided', 'performed_by' => $this->student->id]);
        $this->assertDatabaseHas('email_notifications', ['ticket_id' => $ticket->id, 'type' => 'clarification_provided', 'recipient_email' => $this->admin->email]);

        // The exchange stays visible to the admin during review.
        $this->actingAs($this->admin)
            ->get(route('admin.complaints.show', $ticket->complaint))
            ->assertOk()
            ->assertSee('First semester, 2025-2026.');
    }

    public function test_a_clarification_request_needs_a_message_and_only_the_admin_can_send_it(): void
    {
        $ticket = $this->ticket(Ticket::STATUS_SUBMITTED);

        $this->actingAs($this->admin)
            ->post(route('admin.tickets.clarification', $ticket), ['clarification_message' => ''])
            ->assertSessionHasErrors('clarification_message');

        $this->actingAs($this->handler)
            ->post(route('admin.tickets.clarification', $ticket), ['clarification_message' => 'Hello'])
            ->assertForbidden();

        $this->assertSame(Ticket::STATUS_SUBMITTED, $ticket->fresh()->status);
    }

    public function test_a_ticket_waiting_on_the_student_can_still_be_reviewed(): void
    {
        $ticket = $this->ticket(Ticket::STATUS_NEEDS_CLARIFICATION);

        $this->actingAs($this->admin)
            ->post(route('admin.tickets.reject', $ticket), [
                'closure_type' => Ticket::CLOSURE_NO_RESPONSE,
                'closure_reason' => 'No reply after two weeks.',
            ])
            ->assertSessionHasNoErrors();

        $ticket->refresh();
        $this->assertSame(Ticket::STATUS_CLOSED, $ticket->status);
        $this->assertSame(Ticket::CLOSURE_NO_RESPONSE, $ticket->closure_type);
    }

    // ------------------------------------------------------------------ assignment and handling

    public function test_assigning_to_a_recipient_lands_on_assigned_and_acknowledging_starts_progress(): void
    {
        $ticket = $this->ticket(Ticket::STATUS_SUBMITTED);
        $this->category->suggestedRecipients()->attach($this->handler->recipient->id);

        $this->actingAs($this->admin)
            ->post(route('admin.tickets.assign', $ticket), ['assignment_mode' => 'recipient', 'recipient_id' => $this->handler->recipient->id])
            ->assertSessionHasNoErrors();

        $ticket->refresh();
        $this->assertSame(Ticket::STATUS_ASSIGNED, $ticket->status);
        $this->assertSame($this->handler->id, (int) $ticket->assigned_to);
        $this->assertNull($ticket->acknowledged_at);

        $this->actingAs($this->handler)
            ->get(route('recipient.complaints.show', $ticket->complaint))
            ->assertOk()
            ->assertSee('Acknowledge Ticket')
            ->assertDontSee('Mark as Resolved');

        // It cannot be resolved before it is acknowledged.
        $this->actingAs($this->handler)
            ->patch(route('recipient.complaints.update-status', $ticket->complaint), [
                'status' => 'resolved',
                'resolution_type' => Ticket::RESOLUTION_ACTION_TAKEN,
                'resolution_message' => 'Done.',
            ])
            ->assertForbidden();

        $this->actingAs($this->handler)
            ->post(route('recipient.complaints.acknowledge', $ticket->complaint))
            ->assertSessionHasNoErrors();

        $ticket->refresh();
        $this->assertSame(Ticket::STATUS_IN_PROGRESS, $ticket->status);
        $this->assertNotNull($ticket->acknowledged_at);
    }

    public function test_the_admin_can_take_a_ticket_directly(): void
    {
        $ticket = $this->ticket(Ticket::STATUS_SUBMITTED);

        $this->actingAs($this->admin)->post(route('admin.tickets.acknowledge', $ticket))->assertSessionHasNoErrors();

        $ticket->refresh();
        $this->assertSame(Ticket::STATUS_IN_PROGRESS, $ticket->status);
        $this->assertSame($this->admin->id, (int) $ticket->current_handler_id);
        $this->assertSame(Ticket::JURISDICTION_SDS, $ticket->jurisdiction);
    }

    public function test_a_recipient_cannot_close_reject_or_reset_a_ticket(): void
    {
        $ticket = $this->ticket(Ticket::STATUS_IN_PROGRESS);

        foreach (['closed', 'rejected', 'submitted', 'assigned', 'in_progress'] as $status) {
            $this->actingAs($this->handler)
                ->patch(route('recipient.complaints.update-status', $ticket->complaint), [
                    'status' => $status,
                    'resolution_type' => Ticket::RESOLUTION_ACTION_TAKEN,
                    'resolution_message' => 'Trying another status.',
                ])
                ->assertSessionHasErrors('status');
        }

        $this->actingAs($this->handler)->post(route('admin.tickets.close', $ticket))->assertForbidden();

        $this->assertSame(Ticket::STATUS_IN_PROGRESS, $ticket->fresh()->status);
    }

    public function test_resolving_needs_a_type_and_only_the_handler_can_do_it(): void
    {
        $ticket = $this->ticket(Ticket::STATUS_IN_PROGRESS);
        $url = route('recipient.complaints.update-status', $ticket->complaint);

        $this->actingAs($this->handler)
            ->patch($url, ['status' => 'resolved'])
            ->assertSessionHasErrors('resolution_type');

        $this->actingAs($this->otherRecipient)
            ->patch($url, ['status' => 'resolved', 'resolution_type' => Ticket::RESOLUTION_ACTION_TAKEN, 'resolution_message' => 'Not mine.'])
            ->assertForbidden();

        // The admin is not the handler of this ticket, so cannot resolve it either.
        $this->actingAs($this->admin)
            ->post(route('admin.tickets.resolve', $ticket), ['resolution_type' => Ticket::RESOLUTION_ACTION_TAKEN, 'resolution_message' => 'Not mine.'])
            ->assertForbidden();

        $this->actingAs($this->handler)
            ->patch($url, ['status' => 'resolved', 'resolution_type' => Ticket::RESOLUTION_EXPLANATION, 'resolution_message' => 'Your transcript is ready for pick-up.'])
            ->assertSessionHasNoErrors();

        $ticket->refresh();
        $this->assertSame(Ticket::STATUS_RESOLVED, $ticket->status);
        $this->assertSame(Ticket::RESOLUTION_EXPLANATION, $ticket->resolution_type);
        $this->assertNotNull($ticket->resolved_at);
        $this->assertFalse($ticket->thread->is_active);
        $this->assertDatabaseHas('thread_messages', ['content' => 'Your transcript is ready for pick-up.']);
    }

    public function test_the_admin_resolves_a_ticket_it_handles_directly(): void
    {
        $ticket = $this->ticket(Ticket::STATUS_IN_PROGRESS, ['assigned_to' => null, 'current_handler_id' => $this->admin->id]);

        $this->actingAs($this->admin)
            ->post(route('admin.tickets.resolve', $ticket), ['resolution_message' => 'Handled.'])
            ->assertSessionHasErrors('resolution_type');

        $this->actingAs($this->admin)
            ->post(route('admin.tickets.resolve', $ticket), ['resolution_type' => Ticket::RESOLUTION_SETTLED, 'resolution_message' => 'Handled.'])
            ->assertSessionHasNoErrors();

        $this->assertSame(Ticket::STATUS_RESOLVED, $ticket->fresh()->status);
    }

    public function test_resolving_without_optional_details_does_not_post_an_empty_thread_message(): void
    {
        $ticket = $this->ticket(Ticket::STATUS_IN_PROGRESS, ['assigned_to' => null, 'current_handler_id' => $this->admin->id]);

        $this->actingAs($this->admin)
            ->post(route('admin.tickets.resolve', $ticket), ['resolution_type' => Ticket::RESOLUTION_SETTLED])
            ->assertSessionHasNoErrors();

        $ticket->refresh();
        $this->assertSame(Ticket::STATUS_RESOLVED, $ticket->status);
        $this->assertSame(0, $ticket->thread?->messages()->count() ?? 0);
        $this->assertDatabaseHas('audit_logs', [
            'ticket_id' => $ticket->id,
            'action' => 'complaint_resolved',
            'details' => null,
        ]);
    }

    public function test_a_recipient_can_resolve_without_optional_details(): void
    {
        $ticket = $this->ticket(Ticket::STATUS_IN_PROGRESS);

        $this->actingAs($this->handler)
            ->patch(route('recipient.complaints.update-status', $ticket->complaint), [
                'status' => Ticket::STATUS_RESOLVED,
                'resolution_type' => Ticket::RESOLUTION_SETTLED,
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(Ticket::STATUS_RESOLVED, $ticket->fresh()->status);
    }

    // ------------------------------------------------------------------ after resolution

    public function test_the_student_accepting_the_resolution_closes_the_ticket(): void
    {
        $ticket = $this->ticket(Ticket::STATUS_RESOLVED, ['resolution_type' => Ticket::RESOLUTION_ACTION_TAKEN]);

        $this->actingAs($this->student)
            ->get(route('student.complaints.show', $ticket->complaint))
            ->assertOk()
            ->assertSee('Accept Resolution')
            ->assertSee('Request Further Action')
            ->assertDontSee('Withdraw Ticket');

        $this->actingAs($this->otherStudent)
            ->post(route('student.complaints.accept-resolution', $ticket->complaint))
            ->assertForbidden();

        $this->actingAs($this->student)
            ->post(route('student.complaints.accept-resolution', $ticket->complaint))
            ->assertSessionHasNoErrors();

        $ticket->refresh();
        $this->assertSame(Ticket::STATUS_CLOSED, $ticket->status);
        $this->assertSame(Ticket::CLOSURE_RESOLVED_ACCEPTED, $ticket->closure_type);
        $this->assertNotNull($ticket->closed_at);
        $this->assertDatabaseHas('audit_logs', ['ticket_id' => $ticket->id, 'action' => 'resolution_accepted']);
        $this->assertDatabaseHas('email_notifications', ['ticket_id' => $ticket->id, 'type' => 'resolution_accepted', 'recipient_email' => $this->handler->email]);

        $this->actingAs($this->student)
            ->get(route('student.complaints.show', $ticket->complaint))
            ->assertOk()
            ->assertSee('Resolution accepted by the student')
            ->assertDontSee('Accept Resolution');
    }

    public function test_the_student_can_request_further_action_within_fifteen_days(): void
    {
        $ticket = $this->ticket(Ticket::STATUS_RESOLVED, ['resolved_at' => now()->subDays(14)]);
        $url = route('student.complaints.further-action', $ticket->complaint);

        $this->actingAs($this->student)->post($url, [])->assertSessionHasErrors('further_action_reason');

        $this->actingAs($this->student)
            ->post($url, ['further_action_reason' => 'The record is still wrong.'])
            ->assertSessionHasNoErrors();

        $ticket->refresh();
        $this->assertSame(Ticket::STATUS_IN_PROGRESS, $ticket->status);
        $this->assertNull($ticket->resolved_at);
        $this->assertTrue($ticket->thread->is_active);
        $this->assertDatabaseHas('thread_messages', ['content' => 'The record is still wrong.', 'sender_id' => $this->student->id]);
        $this->assertDatabaseHas('audit_logs', ['ticket_id' => $ticket->id, 'action' => 'further_action_requested']);
        $this->assertDatabaseHas('email_notifications', ['ticket_id' => $ticket->id, 'type' => 'further_action_requested', 'recipient_email' => $this->handler->email]);
    }

    public function test_further_action_is_refused_after_fifteen_days_but_the_resolution_can_still_be_accepted(): void
    {
        $ticket = $this->ticket(Ticket::STATUS_RESOLVED, ['resolved_at' => now()->subDays(16)]);

        $this->actingAs($this->student)
            ->get(route('student.complaints.show', $ticket->complaint))
            ->assertOk()
            ->assertSee('Accept Resolution')
            ->assertDontSee('Request Further Action');

        $this->actingAs($this->student)
            ->post(route('student.complaints.further-action', $ticket->complaint), ['further_action_reason' => 'Too late.'])
            ->assertSessionHasErrors('further_action_reason');

        $this->assertSame(Ticket::STATUS_RESOLVED, $ticket->fresh()->status);

        $this->actingAs($this->student)->post(route('student.complaints.accept-resolution', $ticket->complaint))->assertSessionHasNoErrors();
        $this->assertSame(Ticket::STATUS_CLOSED, $ticket->fresh()->status);
    }

    public function test_the_admin_confirms_or_sends_back_a_resolved_ticket(): void
    {
        $sentBack = $this->ticket(Ticket::STATUS_RESOLVED);
        $this->actingAs($this->admin)->post(route('admin.tickets.not-yet-resolved', $sentBack))->assertSessionHasNoErrors();
        $this->assertSame(Ticket::STATUS_IN_PROGRESS, $sentBack->fresh()->status);

        $confirmed = $this->ticket(Ticket::STATUS_RESOLVED);
        $this->actingAs($this->admin)->post(route('admin.tickets.close', $confirmed))->assertSessionHasNoErrors();
        $confirmed->refresh();
        $this->assertSame(Ticket::STATUS_CLOSED, $confirmed->status);
        $this->assertSame(Ticket::CLOSURE_RESOLVED, $confirmed->closure_type);
    }

    public function test_student_update_requests_are_limited_to_their_current_review_status_period(): void
    {
        $workflow = app(TicketWorkflow::class);
        $ticket = $this->ticket(Ticket::STATUS_SUBMITTED);
        $ticket->complaint->update(['is_anonymous' => true, 'subject_title' => 'Private student complaint title']);
        $ticket->complaint->category->update(['is_sensitive' => true]);
        $lastActionAt = $ticket->lastActionAt();
        $originalHandler = $ticket->current_handler_id;

        $this->actingAs($this->student)
            ->get(route('student.complaints.show', $ticket->complaint))
            ->assertOk()
            ->assertSee('Request Update')
            ->assertDontSee('Request Escalation');

        $this->actingAs($this->student)
            ->post(route('student.complaints.request-update', $ticket->complaint))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success', 'Your request was sent to SDS');

        $ticket->refresh();
        $this->assertSame(Ticket::STATUS_SUBMITTED, $ticket->status);
        $this->assertSame($originalHandler, $ticket->current_handler_id);
        $this->assertSame($lastActionAt?->timestamp, $ticket->lastActionAt()?->timestamp);
        $this->assertDatabaseMissing('ticket_threads', ['ticket_id' => $ticket->id]);
        $this->assertDatabaseHas('audit_logs', [
            'ticket_id' => $ticket->id,
            'performed_by' => $this->student->id,
            'action' => 'update_requested',
            'details' => null,
        ]);
        $this->assertSame(1, app(TicketUnreadService::class)->unreadCountForTicket($this->admin, $ticket));
        $this->assertDatabaseHas('email_notifications', [
            'ticket_id' => $ticket->id,
            'recipient_email' => $this->admin->email,
            'type' => EmailNotification::TYPE_UPDATE_REQUESTED,
            'status' => EmailNotification::STATUS_PENDING,
        ]);
        $this->assertDatabaseMissing('email_notifications', [
            'ticket_id' => $ticket->id,
            'recipient_email' => $this->handler->email,
            'type' => EmailNotification::TYPE_UPDATE_REQUESTED,
        ]);
        $this->assertDatabaseMissing('email_notifications', [
            'ticket_id' => $ticket->id,
            'recipient_email' => $this->student->email,
            'type' => EmailNotification::TYPE_UPDATE_REQUESTED,
        ]);
        $notification = EmailNotification::query()->where([
            'ticket_id' => $ticket->id,
            'recipient_email' => $this->admin->email,
            'type' => EmailNotification::TYPE_UPDATE_REQUESTED,
        ])->firstOrFail();
        $email = app(TicketEmailComposer::class)->compose($notification);
        $emailText = implode(' ', [
            $email['subject'],
            $email['heading'],
            ...$email['lines'],
            ...array_values($email['details']),
            $email['body'],
        ]);
        $this->assertStringContainsString('Confidential concern', $emailText);
        $this->assertStringNotContainsString('Private student complaint title', $emailText);
        $this->assertStringNotContainsString($this->student->name, $emailText);
        $this->assertStringNotContainsString($this->student->email, $emailText);
        $this->assertSame(0, app(TicketUnreadService::class)->unreadCountForTicket($this->handler, $ticket));

        $this->actingAs($this->student)
            ->get(route('student.complaints.show', $ticket->complaint))
            ->assertOk()
            ->assertSee('Your request was sent to SDS')
            ->assertDontSee('Request Update');

        $auditCount = $ticket->auditLogs()->count();
        $emailCount = $ticket->emailNotifications()->count();
        $this->actingAs($this->student)
            ->post(route('student.complaints.request-update', $ticket->complaint), ['update_request_note' => 'Another request.'])
            ->assertSessionHasErrors('update_request');
        $this->assertSame($auditCount, $ticket->auditLogs()->count());
        $this->assertSame($emailCount, $ticket->emailNotifications()->count());

        app(TicketWorkflow::class)->requestClarification($ticket->fresh(), $this->admin, 'Please clarify this detail.');
        $messageCount = $ticket->thread->messages()->count();
        $this->assertContains(TicketWorkflow::REQUEST_UPDATE, $workflow->availableActions($this->student, $ticket->fresh()));

        $this->actingAs($this->student)
            ->post(route('student.complaints.request-update', $ticket->complaint), ['update_request_note' => 'Please share a progress update.'])
            ->assertSessionHasNoErrors();

        $ticket->refresh();
        $this->assertSame(Ticket::STATUS_NEEDS_CLARIFICATION, $ticket->status);
        $this->assertSame($originalHandler, $ticket->current_handler_id);
        $this->assertSame($messageCount, $ticket->thread->messages()->count());
        $this->assertDatabaseHas('audit_logs', [
            'ticket_id' => $ticket->id,
            'action' => 'update_requested',
            'details' => 'Please share a progress update.',
        ]);
        $this->actingAs($this->admin)
            ->get(route('admin.complaints.show', $ticket->complaint))
            ->assertOk()
            ->assertSee('Pending student requests')
            ->assertSee('Student requested an update')
            ->assertSee('Please share a progress update.')
            ->assertDontSee($this->student->name)
            ->assertSee('Private student complaint title');
    }

    public function test_update_and_escalation_requests_are_only_available_in_their_allowed_statuses(): void
    {
        $workflow = app(TicketWorkflow::class);

        foreach ([Ticket::STATUS_SUBMITTED, Ticket::STATUS_NEEDS_CLARIFICATION] as $status) {
            $ticket = $this->ticket($status);
            $this->assertContains(TicketWorkflow::REQUEST_UPDATE, $workflow->availableActions($this->student, $ticket));
            $this->assertNotContains(TicketWorkflow::REQUEST_ESCALATION, $workflow->availableActions($this->student, $ticket));
            $this->actingAs($this->student)
                ->post(route('student.complaints.request-escalation', $ticket->complaint), ['escalation_request_reason' => 'Please consider escalation.'])
                ->assertForbidden();

            $this->actingAs($this->student)
                ->post(route('student.complaints.request-update', $ticket->complaint), ['update_request_note' => str_repeat('n', 501)])
                ->assertSessionHasErrors('update_request_note');
        }

        foreach ([Ticket::STATUS_ASSIGNED, Ticket::STATUS_IN_PROGRESS, Ticket::STATUS_ESCALATED] as $status) {
            $ticket = $this->ticket($status);
            $this->assertContains(TicketWorkflow::REQUEST_ESCALATION, $workflow->availableActions($this->student, $ticket));
            $this->assertNotContains(TicketWorkflow::REQUEST_UPDATE, $workflow->availableActions($this->student, $ticket));
            $this->actingAs($this->student)
                ->post(route('student.complaints.request-update', $ticket->complaint), ['update_request_note' => 'Please send an update.'])
                ->assertForbidden();

            $this->actingAs($this->student)
                ->post(route('student.complaints.request-escalation', $ticket->complaint), [])
                ->assertSessionHasErrors('escalation_request_reason');
            $this->actingAs($this->student)
                ->post(route('student.complaints.request-escalation', $ticket->complaint), ['escalation_request_reason' => 'Too short'])
                ->assertSessionHasErrors('escalation_request_reason');
            $this->actingAs($this->student)
                ->post(route('student.complaints.request-escalation', $ticket->complaint), ['escalation_request_reason' => str_repeat('r', 1001)])
                ->assertSessionHasErrors('escalation_request_reason');
        }

        foreach ([Ticket::STATUS_REFERRED, Ticket::STATUS_RESOLVED, Ticket::STATUS_CLOSED] as $status) {
            $ticket = $this->ticket($status);
            $this->assertNotContains(TicketWorkflow::REQUEST_UPDATE, $workflow->availableActions($this->student, $ticket));
            $this->assertNotContains(TicketWorkflow::REQUEST_ESCALATION, $workflow->availableActions($this->student, $ticket));
            $this->actingAs($this->student)
                ->post(route('student.complaints.request-update', $ticket->complaint), ['update_request_note' => 'Please send an update.'])
                ->assertForbidden();
            $this->actingAs($this->student)
                ->post(route('student.complaints.request-escalation', $ticket->complaint), ['escalation_request_reason' => 'Please consider escalation.'])
                ->assertForbidden();
        }

        foreach ([
            [Ticket::STATUS_SUBMITTED, 'student.complaints.request-update', ['update_request_note' => 'Please send an update.']],
            [Ticket::STATUS_IN_PROGRESS, 'student.complaints.request-escalation', ['escalation_request_reason' => 'Please consider escalation.']],
        ] as [$status, $route, $payload]) {
            $ticket = $this->ticket($status);
            foreach ([$this->otherStudent, $this->handler, $this->otherRecipient] as $user) {
                $this->actingAs($user)
                    ->post(route($route, $ticket->complaint), $payload)
                    ->assertForbidden();
            }

            $closed = $this->ticket(Ticket::STATUS_CLOSED);
            $this->actingAs($this->student)
                ->post(route($route, $closed->complaint), $payload)
                ->assertForbidden();
        }
    }

    public function test_escalation_requests_are_sds_only_pending_until_sds_acts(): void
    {
        $ticket = $this->ticket(Ticket::STATUS_IN_PROGRESS);
        $ticket->complaint->update(['is_anonymous' => true, 'subject_title' => 'Private student conduct matter']);
        $ticket->complaint->category->update(['is_sensitive' => true]);
        $reason = 'The current handling has not addressed the impact on my studies.';
        $originalHandler = $ticket->current_handler_id;
        $messageCount = $ticket->thread->messages()->count();
        $lastActionAt = $ticket->lastActionAt();

        $this->actingAs($this->student)
            ->post(route('student.complaints.request-escalation', $ticket->complaint), ['escalation_request_reason' => $reason])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success', 'Your request was sent to SDS');

        $ticket->refresh();
        $this->assertSame(Ticket::STATUS_IN_PROGRESS, $ticket->status);
        $this->assertSame($originalHandler, $ticket->current_handler_id);
        $this->assertSame($messageCount, $ticket->thread->messages()->count());
        $this->assertSame($lastActionAt?->timestamp, $ticket->lastActionAt()?->timestamp);
        $this->assertTrue(app(TicketWorkflow::class)->hasPendingEscalationRequest($ticket));
        $this->assertSame(1, app(TicketUnreadService::class)->unreadCountForTicket($this->admin, $ticket));
        $this->assertSame(0, app(TicketUnreadService::class)->unreadCountForTicket($this->handler, $ticket));
        $this->assertDatabaseHas('email_notifications', [
            'ticket_id' => $ticket->id,
            'recipient_email' => $this->admin->email,
            'type' => EmailNotification::TYPE_ESCALATION_REQUESTED,
            'status' => EmailNotification::STATUS_PENDING,
        ]);
        $this->assertDatabaseMissing('email_notifications', [
            'ticket_id' => $ticket->id,
            'recipient_email' => $this->handler->email,
            'type' => EmailNotification::TYPE_ESCALATION_REQUESTED,
        ]);

        $this->actingAs($this->student)
            ->get(route('student.complaints.show', $ticket->complaint))
            ->assertOk()
            ->assertSee('Your request was sent to SDS')
            ->assertDontSee('Request Escalation');
        $this->actingAs($this->admin)
            ->get(route('admin.complaints.show', $ticket->complaint))
            ->assertOk()
            ->assertSee('Pending student requests')
            ->assertSee('Student requested escalation')
            ->assertSee($reason)
            ->assertDontSee($this->student->name)
            ->assertDontSee($this->student->email)
            ->assertSee('Private student conduct matter');
        $this->actingAs($this->handler)
            ->get(route('recipient.complaints.show', $ticket->complaint))
            ->assertOk()
            ->assertDontSee($reason);
        $auditCount = $ticket->auditLogs()->count();
        $emailCount = $ticket->emailNotifications()->count();
        $this->actingAs($this->student)
            ->post(route('student.complaints.request-escalation', $ticket->complaint), ['escalation_request_reason' => 'A second escalation request.'])
            ->assertSessionHasErrors('escalation_request');
        $this->assertSame($auditCount, $ticket->auditLogs()->count());
        $this->assertSame($emailCount, $ticket->emailNotifications()->count());

        $notification = EmailNotification::query()->where([
            'ticket_id' => $ticket->id,
            'recipient_email' => $this->admin->email,
            'type' => EmailNotification::TYPE_ESCALATION_REQUESTED,
        ])->firstOrFail();
        $email = app(TicketEmailComposer::class)->compose($notification);
        $emailText = implode(' ', [
            $email['subject'],
            $email['heading'],
            ...$email['lines'],
            ...array_values($email['details']),
            $email['body'],
        ]);
        $this->assertStringContainsString('Confidential concern', $emailText);
        $this->assertStringNotContainsString('Private student conduct matter', $emailText);
        $this->assertStringNotContainsString($reason, $emailText);
        $this->assertStringNotContainsString($this->student->name, $emailText);
        $this->assertStringNotContainsString($this->student->email, $emailText);

        $ticket->complaint->category->escalationHierarchies()->create([
            'path_number' => 1,
            'level' => 1,
            'recipient_id' => $this->otherRecipient->recipient->id,
        ]);
        $this->actingAs($this->admin)
            ->post(route('admin.tickets.escalate', $ticket), [
                'recipient_id' => $this->otherRecipient->recipient->id,
                'escalation_note' => 'The ticket needs a higher review level.',
            ])
            ->assertSessionHasNoErrors();

        $ticket->refresh();
        $this->assertSame(Ticket::STATUS_ESCALATED, $ticket->status);
        $this->assertFalse(app(TicketWorkflow::class)->hasPendingEscalationRequest($ticket));
        $this->actingAs($this->student)
            ->get(route('student.complaints.show', $ticket->complaint))
            ->assertOk()
            ->assertDontSee('Your request was sent to SDS')
            ->assertSee('Request Escalation');
    }

    public function test_only_an_sds_reply_clears_a_pending_escalation_request(): void
    {
        $ticket = $this->ticket(Ticket::STATUS_ASSIGNED);
        app(TicketWorkflow::class)->requestEscalation($ticket, $this->student, 'Please review this assignment again.');

        AuditLog::log($ticket->id, 'message_posted', $this->handler->id, 'Recipient posted a reply message.');
        $this->assertTrue(app(TicketWorkflow::class)->hasPendingEscalationRequest($ticket->fresh()));

        AuditLog::log($ticket->id, 'message_posted', $this->admin->id, 'Admin posted a reply message.');
        $this->assertFalse(app(TicketWorkflow::class)->hasPendingEscalationRequest($ticket->fresh()));
        $this->assertTrue(app(TicketWorkflow::class)->can($this->student, $ticket->fresh(), TicketWorkflow::REQUEST_ESCALATION));
    }

    // ------------------------------------------------------------------ withdrawal and closing

    public function test_the_student_can_withdraw_before_resolution_only(): void
    {
        foreach ([Ticket::STATUS_SUBMITTED, Ticket::STATUS_NEEDS_CLARIFICATION, Ticket::STATUS_ASSIGNED, Ticket::STATUS_IN_PROGRESS, Ticket::STATUS_ESCALATED] as $status) {
            $ticket = $this->ticket($status);

            $this->actingAs($this->otherStudent)->post(route('student.complaints.withdraw', $ticket->complaint))->assertForbidden();

            $this->actingAs($this->student)
                ->post(route('student.complaints.withdraw', $ticket->complaint))
                ->assertSessionHasNoErrors();

            $ticket->refresh();
            $this->assertSame(Ticket::STATUS_CLOSED, $ticket->status, $status);
            $this->assertSame(Ticket::CLOSURE_WITHDRAWN, $ticket->closure_type);
            $this->assertNull($ticket->closure_reason);
            $this->assertDatabaseHas('audit_logs', ['ticket_id' => $ticket->id, 'action' => 'ticket_withdrawn']);
        }

        $referred = $this->ticket(Ticket::STATUS_REFERRED);
        $this->actingAs($this->student)
            ->post(route('student.complaints.withdraw', $referred->complaint))
            ->assertForbidden();
        $this->assertSame(Ticket::STATUS_REFERRED, $referred->fresh()->status);

        foreach ([Ticket::STATUS_RESOLVED, Ticket::STATUS_CLOSED] as $status) {
            $ticket = $this->ticket($status);

            $this->actingAs($this->student)->post(route('student.complaints.withdraw', $ticket->complaint))->assertForbidden();
            $this->assertSame($status, $ticket->fresh()->status);
        }
    }

    public function test_a_referred_student_can_only_request_withdrawal_with_a_reason_and_sds_is_notified(): void
    {
        $ticket = $this->ticket(Ticket::STATUS_REFERRED);
        AuditLog::log($ticket->id, 'ticket_referred', $this->admin->id, 'Ticket referred to Student Disciplinary Board.');
        $ticket->complaint->update(['is_anonymous' => true]);
        $ticket->complaint->category->update(['is_sensitive' => true]);
        $reason = 'I no longer want to continue this concern.';
        $workflow = app(TicketWorkflow::class);

        $this->assertContains(TicketWorkflow::REQUEST_WITHDRAWAL, $workflow->availableActions($this->student, $ticket));
        $this->assertNotContains(TicketWorkflow::WITHDRAW, $workflow->availableActions($this->student, $ticket));

        $this->actingAs($this->student)
            ->post(route('student.complaints.request-withdrawal', $ticket->complaint), [])
            ->assertSessionHasErrors('withdrawal_request_reason');

        $this->actingAs($this->student)
            ->post(route('student.complaints.request-withdrawal', $ticket->complaint), ['withdrawal_request_reason' => $reason])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success', 'Your request was sent to SDS. Because your concern was referred to a committee, it may continue even if you withdraw.');

        $ticket->refresh();
        $this->assertSame(Ticket::STATUS_REFERRED, $ticket->status);
        $this->assertDatabaseHas('thread_messages', [
            'thread_id' => $ticket->thread->id,
            'sender_id' => $this->student->id,
            'content' => $reason,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'ticket_id' => $ticket->id,
            'performed_by' => $this->student->id,
            'action' => 'withdrawal_requested',
            'details' => 'The student requested withdrawal after referral.',
        ]);
        $this->assertSame(1, app(TicketUnreadService::class)->unreadCountForTicket($this->admin, $ticket));
        $notification = EmailNotification::query()->where([
            'ticket_id' => $ticket->id,
            'recipient_email' => $this->admin->email,
            'type' => EmailNotification::TYPE_WITHDRAWAL_REQUESTED,
            'status' => EmailNotification::STATUS_PENDING,
        ])->firstOrFail();
        $this->assertDatabaseMissing('email_notifications', [
            'ticket_id' => $ticket->id,
            'recipient_email' => $this->student->email,
            'type' => EmailNotification::TYPE_WITHDRAWAL_REQUESTED,
        ]);
        $adminEmail = app(TicketEmailComposer::class)->compose($notification);
        $adminEmailText = implode(' ', [
            $adminEmail['subject'],
            $adminEmail['heading'],
            ...$adminEmail['lines'],
            ...array_values($adminEmail['details']),
        ]);
        $this->assertStringNotContainsString($this->student->name, $adminEmailText);
        $this->assertStringNotContainsString($this->student->email, $adminEmailText);

        $this->actingAs($this->student)
            ->get(route('student.complaints.show', $ticket->complaint))
            ->assertOk()
            ->assertSee('Your withdrawal request has been sent to SDS')
            ->assertDontSee('Request Withdrawal')
            ->assertDontSee('Withdraw Ticket');

        $messageCount = $ticket->thread->messages()->count();
        $auditCount = $ticket->auditLogs()->count();
        $emailCount = $ticket->emailNotifications()->count();
        $this->actingAs($this->student)
            ->post(route('student.complaints.request-withdrawal', $ticket->complaint), ['withdrawal_request_reason' => 'A second request.'])
            ->assertSessionHasErrors('withdrawal_request');

        $ticket->refresh();
        $this->assertSame(Ticket::STATUS_REFERRED, $ticket->status);
        $this->assertSame($messageCount, $ticket->thread->messages()->count());
        $this->assertSame($auditCount, $ticket->auditLogs()->count());
        $this->assertSame($emailCount, $ticket->emailNotifications()->count());

        $this->actingAs($this->admin)
            ->post(route('admin.tickets.outcome', $ticket), ['outcome' => 'The committee completed its review.'])
            ->assertSessionHasNoErrors();
        $this->assertSame(Ticket::STATUS_RESOLVED, $ticket->fresh()->status);
    }

    public function test_a_new_referral_period_allows_a_new_withdrawal_request(): void
    {
        $ticket = $this->ticket(Ticket::STATUS_REFERRED);
        AuditLog::log($ticket->id, 'ticket_referred', $this->admin->id, 'Ticket referred to Student Disciplinary Board.');
        $workflow = app(TicketWorkflow::class);
        $workflow->requestWithdrawal($ticket, $this->student, 'First referral period request.');

        $workflow->recordOutcome($ticket->fresh(), $this->admin, 'The committee completed its review.');
        $workflow->reopen($ticket->fresh(), $this->admin);
        $workflow->refer($ticket->fresh(), $this->admin, 'Campus Review Board', 'Further review is needed.');

        $this->assertTrue($workflow->can($this->student, $ticket->fresh(), TicketWorkflow::REQUEST_WITHDRAWAL));
        $workflow->requestWithdrawal($ticket->fresh(), $this->student, 'Request for the new referral period.');

        $this->assertSame(2, $ticket->auditLogs()->where('action', 'withdrawal_requested')->count());
    }

    public function test_other_codi_names_are_normalized_and_unrelated_names_are_not_flagged(): void
    {
        foreach ([
            'CODI',
            'codi',
            'C.O.D.I.',
            ' Committee   on Decorum and Investigation ',
            'CODI Support Desk',
        ] as $destination) {
            $ticket = $this->ticket(Ticket::STATUS_IN_PROGRESS);

            $this->actingAs($this->admin)
                ->post(route('admin.tickets.refer', $ticket), [
                    'referred_to' => 'other',
                    'referred_to_other' => $destination,
                    'referral_note' => 'The appropriate body will review this ticket.',
                ])
                ->assertSessionHasNoErrors();

            $matchesCodi = ! in_array($destination, ['CODI Support Desk'], true);
            $this->assertSame(
                $matchesCodi ? Ticket::REFERRAL_DESTINATION_CODI : $destination,
                $ticket->fresh()->referred_to
            );
            $this->assertSame($matchesCodi, $ticket->fresh()->is_codi_referral);
        }
    }

    public function test_codi_referral_backfill_normalizes_existing_names_idempotently(): void
    {
        $codi = $this->ticket(Ticket::STATUS_REFERRED, [
            'referred_to' => ' C.O.D.I. ',
            'is_codi_referral' => false,
        ]);
        $unrelated = $this->ticket(Ticket::STATUS_REFERRED, [
            'referred_to' => 'CODI Support Desk',
            'is_codi_referral' => false,
        ]);
        $migration = require database_path('migrations/2026_11_09_200000_normalize_codi_referrals.php');

        $migration->up();
        $migration->up();

        $this->assertSame(Ticket::REFERRAL_DESTINATION_CODI, $codi->fresh()->referred_to);
        $this->assertTrue($codi->fresh()->is_codi_referral);
        $this->assertSame('CODI Support Desk', $unrelated->fresh()->referred_to);
        $this->assertFalse($unrelated->fresh()->is_codi_referral);
    }

    public function test_a_non_referred_student_cannot_request_withdrawal(): void
    {
        foreach ([Ticket::STATUS_SUBMITTED, Ticket::STATUS_NEEDS_CLARIFICATION, Ticket::STATUS_ASSIGNED, Ticket::STATUS_IN_PROGRESS, Ticket::STATUS_ESCALATED, Ticket::STATUS_RESOLVED, Ticket::STATUS_CLOSED] as $status) {
            $ticket = $this->ticket($status);

            $this->actingAs($this->student)
                ->post(route('student.complaints.request-withdrawal', $ticket->complaint), ['withdrawal_request_reason' => 'Please withdraw my concern.'])
                ->assertForbidden();
            $this->assertSame($status, $ticket->fresh()->status);
        }
    }

    public function test_closing_an_unresolved_ticket_needs_a_closure_type_and_reason(): void
    {
        $ticket = $this->ticket(Ticket::STATUS_IN_PROGRESS);

        $this->actingAs($this->admin)
            ->post(route('admin.tickets.close', $ticket))
            ->assertSessionHasErrors('closure_type');

        $this->actingAs($this->admin)
            ->post(route('admin.tickets.close', $ticket), ['closure_type' => Ticket::CLOSURE_RESOLVED_ACCEPTED, 'closure_reason' => 'Not a valid type here.'])
            ->assertSessionHasErrors('closure_type');

        $this->assertSame(Ticket::STATUS_IN_PROGRESS, $ticket->fresh()->status);

        $this->actingAs($this->admin)
            ->post(route('admin.tickets.close', $ticket), ['closure_type' => Ticket::CLOSURE_DUPLICATE, 'closure_reason' => 'Same as SU-2026-00001.'])
            ->assertSessionHasNoErrors();

        $ticket->refresh();
        $this->assertSame(Ticket::STATUS_CLOSED, $ticket->status);
        $this->assertSame(Ticket::CLOSURE_DUPLICATE, $ticket->closure_type);
        $this->assertSame('Same as SU-2026-00001.', $ticket->closure_reason);
        $this->assertFalse($ticket->thread->is_active);
    }

    public function test_admin_closures_requiring_an_explanation_reject_missing_or_invalid_reasons(): void
    {
        foreach ([
            Ticket::CLOSURE_INVALID,
            Ticket::CLOSURE_DUPLICATE,
            Ticket::CLOSURE_OUT_OF_SCOPE,
            Ticket::CLOSURE_NO_RESPONSE,
        ] as $closureType) {
            $ticket = $this->ticket(Ticket::STATUS_IN_PROGRESS);
            $url = route('admin.tickets.close', $ticket);

            $this->actingAs($this->admin)
                ->post($url, ['closure_type' => $closureType])
                ->assertSessionHasErrors('closure_reason');

            $this->actingAs($this->admin)
                ->post($url, ['closure_type' => $closureType, 'closure_reason' => 'Too short'])
                ->assertSessionHasErrors('closure_reason');

            $this->actingAs($this->admin)
                ->post($url, ['closure_type' => $closureType, 'closure_reason' => str_repeat('x', 1001)])
                ->assertSessionHasErrors('closure_reason');

            $reason = 'A written explanation for ' . $closureType . ' closure.';
            $this->actingAs($this->admin)
                ->post($url, ['closure_type' => $closureType, 'closure_reason' => $reason])
                ->assertSessionHasNoErrors();

            $this->assertSame(Ticket::CLOSURE_LABELS[$closureType], $ticket->fresh()->closure_label);
            $this->assertSame($reason, $ticket->fresh()->closure_reason);
        }
    }

    public function test_a_ticket_under_review_is_closed_with_the_reason_it_cannot_be_acted_on(): void
    {
        $ticket = $this->ticket(Ticket::STATUS_SUBMITTED);

        $this->actingAs($this->admin)
            ->post(route('admin.tickets.reject', $ticket), ['closure_type' => Ticket::CLOSURE_OUT_OF_SCOPE])
            ->assertSessionHasErrors('closure_reason');

        $this->actingAs($this->admin)
            ->post(route('admin.tickets.reject', $ticket), ['closure_type' => Ticket::CLOSURE_OUT_OF_SCOPE, 'closure_reason' => 'This concerns a private landlord.'])
            ->assertSessionHasNoErrors();

        $ticket->refresh();
        $this->assertSame(Ticket::STATUS_CLOSED, $ticket->status);
        $this->assertSame(Ticket::CLOSURE_OUT_OF_SCOPE, $ticket->closure_type);
        $this->assertSame(Ticket::CLASSIFICATION_INVALID, $ticket->classification);

        $this->actingAs($this->student)
            ->get(route('student.complaints.show', $ticket->complaint))
            ->assertOk()
            ->assertSee('Outside the scope of the university')
            ->assertSee('This concerns a private landlord.');
    }

    public function test_a_ticket_under_review_cannot_be_rejected_without_an_explanation(): void
    {
        $ticket = $this->ticket(Ticket::STATUS_SUBMITTED);

        $this->actingAs($this->admin)
            ->post(route('admin.tickets.reject', $ticket))
            ->assertSessionHasErrors('closure_reason');

        $ticket->refresh();
        $this->assertSame(Ticket::STATUS_SUBMITTED, $ticket->status);

        $this->actingAs($this->admin)
            ->post(route('admin.tickets.reject', $ticket), ['closure_reason' => 'This complaint is not valid.'])
            ->assertSessionHasNoErrors();

        $this->assertSame(Ticket::CLOSURE_INVALID, $ticket->fresh()->closure_type);
        $this->assertSame('This complaint is not valid.', $ticket->fresh()->closure_reason);
    }

    public function test_informational_tickets_close_as_recorded_for_information(): void
    {
        $ticket = $this->ticket(Ticket::STATUS_SUBMITTED);

        $this->actingAs($this->admin)->post(route('admin.tickets.retain-informational', $ticket))->assertSessionHasNoErrors();

        $ticket->refresh();
        $this->assertSame(Ticket::STATUS_CLOSED, $ticket->status);
        $this->assertSame(Ticket::CLOSURE_INFORMATIONAL, $ticket->closure_type);
    }

    public function test_admin_can_choose_withdrawn_closure_without_an_explanation(): void
    {
        $ticket = $this->ticket(Ticket::STATUS_IN_PROGRESS);

        $this->actingAs($this->admin)
            ->post(route('admin.tickets.close', $ticket), ['closure_type' => Ticket::CLOSURE_WITHDRAWN])
            ->assertSessionHasNoErrors();

        $this->assertSame(Ticket::CLOSURE_WITHDRAWN, $ticket->fresh()->closure_type);
        $this->assertNull($ticket->fresh()->closure_reason);
    }

    // ------------------------------------------------------------------ referral

    public function test_escalating_and_referring_need_an_explanation(): void
    {
        $ticket = $this->ticket(Ticket::STATUS_IN_PROGRESS);
        $ticket->complaint->category->escalationHierarchies()->create(['path_number' => 1, 'level' => 1, 'recipient_id' => $this->otherRecipient->recipient->id]);

        $this->actingAs($this->admin)
            ->post(route('admin.tickets.escalate', $ticket), ['recipient_id' => $this->otherRecipient->recipient->id])
            ->assertSessionHasErrors('escalation_note');
        $this->actingAs($this->admin)
            ->post(route('admin.tickets.refer', $ticket), [
                'referred_to' => Ticket::REFERRAL_DESTINATION_CAMPUS_DISCIPLINARY,
                'dean_endorsed' => '1',
            ])
            ->assertSessionHasErrors('referral_note');
        $this->actingAs($this->admin)
            ->post(route('admin.tickets.refer', $ticket), ['referred_to' => 'other', 'referral_note' => 'Needs a formal hearing.'])
            ->assertSessionHasErrors('referred_to_other');
        $this->assertSame(Ticket::STATUS_IN_PROGRESS, $ticket->fresh()->status);

        // The explanation is kept on the ticket's history.
        $this->actingAs($this->admin)
            ->post(route('admin.tickets.escalate', $ticket), ['recipient_id' => $this->otherRecipient->recipient->id, 'escalation_note' => 'The office could not settle it.'])
            ->assertSessionHasNoErrors();
        $this->assertTrue($ticket->auditLogs()->where('action', 'ticket_escalated')->where('details', 'like', '%Reason: The office could not settle it.')->exists());

        $this->actingAs($this->admin)
            ->post(route('admin.tickets.refer', $ticket), ['referred_to' => 'other', 'referred_to_other' => 'Scholarship Committee', 'referral_note' => 'Needs a formal hearing.'])
            ->assertSessionHasNoErrors();
        $this->assertSame('Scholarship Committee', $ticket->fresh()->referred_to);
        $this->assertTrue($ticket->auditLogs()->where('action', 'ticket_referred')->where('details', 'like', '%Needs a formal hearing.')->exists());
    }

    public function test_a_ticket_is_referred_to_a_committee_and_resolved_with_its_outcome(): void
    {
        $ticket = $this->ticket(Ticket::STATUS_IN_PROGRESS);

        $this->actingAs($this->admin)
            ->post(route('admin.tickets.refer', $ticket), [])
            ->assertSessionHasErrors('referred_to');

        $this->actingAs($this->handler)
            ->post(route('admin.tickets.refer', $ticket), ['referred_to' => 'Student Disciplinary Board'])
            ->assertForbidden();

        $this->actingAs($this->admin)
            ->post(route('admin.tickets.refer', $ticket), ['referred_to' => 'Student Disciplinary Board', 'referral_note' => 'For formal investigation.'])
            ->assertSessionHasNoErrors();

        $ticket->refresh();
        $this->assertSame(Ticket::STATUS_REFERRED, $ticket->status);
        $this->assertSame('Student Disciplinary Board', $ticket->referred_to);
        $this->assertFalse($ticket->is_codi_referral);
        $this->assertNotNull($ticket->referred_at);
        $this->assertDatabaseHas('audit_logs', ['ticket_id' => $ticket->id, 'action' => 'ticket_referred']);
        $this->assertDatabaseHas('email_notifications', ['ticket_id' => $ticket->id, 'type' => 'referred', 'recipient_email' => $this->student->email]);

        // While referred, the recipient cannot resolve it and it cannot be escalated.
        $this->actingAs($this->handler)
            ->patch(route('recipient.complaints.update-status', $ticket->complaint), ['status' => 'resolved', 'resolution_type' => Ticket::RESOLUTION_ACTION_TAKEN, 'resolution_message' => 'Done.'])
            ->assertForbidden();
        $this->actingAs($this->admin)
            ->post(route('admin.tickets.escalate', $ticket), ['recipient_id' => $this->otherRecipient->recipient->id, 'escalation_note' => 'Not resolved at this level.'])
            ->assertForbidden();

        $this->actingAs($this->student)
            ->get(route('student.complaints.show', $ticket->complaint))
            ->assertOk()
            ->assertSee('Referred')
            ->assertSee('Student Disciplinary Board');

        $codiTicket = $this->ticket(Ticket::STATUS_IN_PROGRESS);
        $this->actingAs($this->admin)
            ->post(route('admin.tickets.refer', $codiTicket), [
                'referred_to' => Ticket::REFERRAL_DESTINATION_CODI,
                'referral_note' => 'Refer this harassment complaint to CODI.',
            ])
            ->assertSessionHasNoErrors();
        $this->assertTrue($codiTicket->fresh()->is_codi_referral);

        $this->actingAs($this->admin)
            ->post(route('admin.tickets.outcome', $ticket), [])
            ->assertSessionHasErrors('outcome');

        $this->actingAs($this->admin)
            ->post(route('admin.tickets.outcome', $ticket), ['outcome' => 'The board issued a written reprimand.'])
            ->assertSessionHasNoErrors();

        $ticket->refresh();
        $this->assertSame(Ticket::STATUS_RESOLVED, $ticket->status);
        $this->assertSame(Ticket::RESOLUTION_COMMITTEE_DECISION, $ticket->resolution_type);
        $this->assertDatabaseHas('thread_messages', ['content' => 'The board issued a written reprimand.']);
        $this->assertDatabaseHas('audit_logs', ['ticket_id' => $ticket->id, 'action' => 'referral_outcome_recorded']);
    }

    public function test_campus_disciplinary_referral_requires_and_audits_dean_endorsement_for_any_category(): void
    {
        $categories = [
            $this->category,
            ComplaintCategory::create(['name' => 'Complaint Against a Student', 'is_active' => true]),
        ];

        foreach ($categories as $category) {
            $ticket = $this->ticket(Ticket::STATUS_IN_PROGRESS);
            $ticket->complaint->update(['category_id' => $category->id]);
            $payload = [
                'referred_to' => Ticket::REFERRAL_DESTINATION_CAMPUS_DISCIPLINARY,
                'referral_note' => 'The College Dean assessed this matter for referral.',
            ];

            $this->actingAs($this->admin)
                ->post(route('admin.tickets.refer', $ticket), $payload)
                ->assertSessionHasErrors('dean_endorsed');
            $this->assertSame(Ticket::STATUS_IN_PROGRESS, $ticket->fresh()->status);

            $this->actingAs($this->admin)
                ->post(route('admin.tickets.refer', $ticket), $payload + ['dean_endorsed' => '1'])
                ->assertSessionHasNoErrors();

            $ticket->refresh();
            $this->assertSame(Ticket::STATUS_REFERRED, $ticket->status);
            $this->assertTrue($ticket->auditLogs()
                ->where('action', 'ticket_referred')
                ->where('details', 'like', '%College Dean assessed this matter and endorsed it to the Campus Disciplinary Committee%')
                ->exists());
        }
    }

    public function test_other_referral_destinations_do_not_require_dean_endorsement(): void
    {
        foreach ([
            [Ticket::REFERRAL_DESTINATION_CODI, null],
            ['University Investigation and Disciplinary Committee', null],
            ['other', 'Student Appeals Board'],
        ] as [$destination, $otherDestination]) {
            $ticket = $this->ticket(Ticket::STATUS_IN_PROGRESS);
            $payload = [
                'referred_to' => $destination,
                'referral_note' => 'The case is being sent to the appropriate body.',
            ];
            if ($otherDestination !== null) {
                $payload['referred_to_other'] = $otherDestination;
            }

            $this->actingAs($this->admin)
                ->post(route('admin.tickets.refer', $ticket), $payload)
                ->assertSessionHasNoErrors();

            $expectedDestination = $otherDestination ?? $destination;
            $this->assertSame($expectedDestination, $ticket->fresh()->referred_to);
        }
    }

    public function test_other_destination_matching_campus_disciplinary_committee_requires_endorsement(): void
    {
        foreach ([' campus   disciplinary committee ', 'CAMPUS-DISCIPLINARY COMMITTEE'] as $typedDestination) {
            $ticket = $this->ticket(Ticket::STATUS_IN_PROGRESS);
            $payload = [
                'referred_to' => 'other',
                'referred_to_other' => $typedDestination,
                'referral_note' => 'The College Dean assessed this matter for referral.',
            ];

            $this->actingAs($this->admin)
                ->post(route('admin.tickets.refer', $ticket), $payload)
                ->assertSessionHasErrors('dean_endorsed');
            $this->assertSame(Ticket::STATUS_IN_PROGRESS, $ticket->fresh()->status);

            $this->actingAs($this->admin)
                ->post(route('admin.tickets.refer', $ticket), $payload + ['dean_endorsed' => '1'])
                ->assertSessionHasNoErrors();

            $this->assertSame(Ticket::REFERRAL_DESTINATION_CAMPUS_DISCIPLINARY, $ticket->fresh()->referred_to);
        }
    }

    // ------------------------------------------------------------------ escalation

    public function test_escalating_twice_keeps_the_escalated_status(): void
    {
        $ticket = $this->ticket(Ticket::STATUS_IN_PROGRESS);
        $third = $this->user(User::ROLE_RECIPIENT, 'Cora Chair');
        Recipient::create(['user_id' => $third->id, 'staff_id' => 'STAFF-9', 'unit' => 'CICT', 'designation' => 'Chair']);

        foreach ([[1, $this->otherRecipient], [2, $third]] as [$level, $user]) {
            $this->category->escalationHierarchies()->create(['recipient_id' => $user->recipient->id, 'level' => $level]);
        }

        $this->actingAs($this->admin)
            ->post(route('admin.tickets.escalate', $ticket), ['recipient_id' => $this->otherRecipient->recipient->id, 'escalation_note' => 'Not resolved at this level.'])
            ->assertSessionHasNoErrors();
        $this->assertSame(Ticket::STATUS_ESCALATED, $ticket->fresh()->status);

        $this->actingAs($this->admin)
            ->post(route('admin.tickets.escalate', $ticket), ['recipient_id' => $third->recipient->id, 'escalation_note' => 'Not resolved at this level.'])
            ->assertSessionHasNoErrors();

        $ticket->refresh();
        $this->assertSame(Ticket::STATUS_ESCALATED, $ticket->status);
        $this->assertSame($third->id, (int) $ticket->assigned_to);

        // The person it was escalated to can resolve it.
        $this->actingAs($third)
            ->patch(route('recipient.complaints.update-status', $ticket->complaint), ['status' => 'resolved', 'resolution_type' => Ticket::RESOLUTION_ACTION_TAKEN, 'resolution_message' => 'Settled at the college.'])
            ->assertSessionHasNoErrors();
        $this->assertSame(Ticket::STATUS_RESOLVED, $ticket->fresh()->status);
    }

    // ------------------------------------------------------------------ screens

    public function test_every_screen_renders_for_a_ticket_in_every_status(): void
    {
        foreach (Ticket::STATUS_LABELS as $status => $label) {
            $ticket = $this->ticket($status, $status === Ticket::STATUS_CLOSED ? ['closure_type' => Ticket::CLOSURE_RESOLVED, 'closed_at' => now()] : []);
            $complaint = $ticket->complaint;

            $displayLabel = $ticket->displayStatus();
            $this->actingAs($this->student)->get(route('student.complaints.show', $complaint))->assertOk()->assertSee($displayLabel);
            $this->actingAs($this->admin)->get(route('admin.complaints.show', $complaint))->assertOk()->assertSee($displayLabel);

            if (! $ticket->isAwaitingReview()) {
                $this->actingAs($this->handler)->get(route('recipient.complaints.show', $complaint))->assertOk()->assertSee($displayLabel);
            }
        }

        $this->actingAs($this->student)->get(route('student.complaints.index'))->assertOk()->assertSee('In review')->assertSee('Referred');
        $this->actingAs($this->student)->get(route('student.dashboard'))->assertOk();
        $this->actingAs($this->handler)->get(route('recipient.tickets.index'))->assertOk()->assertSee('Referred');
        $this->actingAs($this->handler)->get(route('recipient.dashboard'))->assertOk();
        $this->actingAs($this->admin)->get(route('admin.tickets.review.index'))->assertOk()->assertSee('Ask for Details');
        $this->actingAs($this->admin)->get(route('admin.complaints.index'))->assertOk()->assertSee('In review');
        $this->actingAs($this->admin)->get(route('admin.dashboard'))->assertOk()->assertDontSee('Rejected');
        $this->actingAs($this->admin)->get('/admin/analytics')->assertOk();
    }

    public function test_ticket_details_show_review_metadata_before_the_complaint_narrative(): void
    {
        $ticket = $this->ticket(Ticket::STATUS_IN_PROGRESS);
        $staffDetailsOrder = [
            'Category',
            'Assigned Recipient',
            'Classification',
            'Last Updated At',
            'Created At',
            'Created By',
            'Days Open',
            'Complaint details',
            'My transcript has not been released.',
        ];

        $this->actingAs($this->student)
            ->get(route('student.complaints.show', $ticket->complaint))
            ->assertOk()
            ->assertSeeInOrder([
                'Category',
                'Assigned Recipient',
                'Classification',
                'Last updated',
                'Current office',
                'Waiting for',
                'Created At',
                'Created By',
                'Days Open',
                'Complaint details',
                'My transcript has not been released.',
            ]);

        foreach ([
            [$this->handler, route('recipient.complaints.show', $ticket->complaint)],
            [$this->admin, route('admin.complaints.show', $ticket->complaint)],
        ] as [$user, $url]) {
            $this->actingAs($user)->get($url)->assertOk()->assertSeeInOrder($staffDetailsOrder);
        }

        $this->actingAs($this->admin)
            ->get(route('admin.complaints.index'))
            ->assertOk()
            ->assertSeeInOrder($staffDetailsOrder);

        $this->ticket(Ticket::STATUS_IN_PROGRESS, [
            'assigned_to' => null,
            'current_handler_id' => $this->admin->id,
        ]);
        $this->actingAs($this->admin)
            ->get(route('admin.tickets.my'))
            ->assertOk()
            ->assertSeeInOrder([
                'Category',
                'Assigned To',
                'Classification',
                'Last Updated At',
                'Created At',
                'Created By',
                'Days Open',
                'Complaint details',
                'My transcript has not been released.',
            ]);

        $this->ticket(Ticket::STATUS_SUBMITTED);
        $this->actingAs($this->admin)
            ->get(route('admin.tickets.review.index'))
            ->assertOk()
            ->assertSeeInOrder([
                'Category',
                'Suggested Recipient',
                'Classification',
                'Last Updated At',
                'Created At',
                'Created By',
                'Days Open',
                'Complaint details',
                'My transcript has not been released.',
            ]);
    }

    public function test_the_admin_sees_the_actions_for_tickets_it_handles(): void
    {
        $mine = $this->ticket(Ticket::STATUS_IN_PROGRESS, ['assigned_to' => null, 'current_handler_id' => $this->admin->id]);

        $this->actingAs($this->admin)->get(route('admin.tickets.my'))->assertOk()
            ->assertSee('Mark as Resolved')
            ->assertSee('Refer to Committee')
            ->assertSee('Close Ticket');

        $this->actingAs($this->admin)->get(route('admin.complaints.index'))->assertOk()
            ->assertSee('Mark as Resolved')
            ->assertSee('Refer to Committee');

        $this->actingAs($this->admin)->get(route('admin.complaints.show', $mine->complaint))->assertOk()
            ->assertSee('Mark as Resolved')
            ->assertSee('Refer to Committee');

        $referred = $this->ticket(Ticket::STATUS_REFERRED);
        $this->actingAs($this->admin)->get(route('admin.complaints.show', $referred->complaint))->assertOk()
            ->assertSee('Record Outcome')
            ->assertDontSee('Mark as Resolved');

        $resolved = $this->ticket(Ticket::STATUS_RESOLVED);
        $this->actingAs($this->admin)->get(route('admin.complaints.show', $resolved->complaint))->assertOk()
            ->assertSee('Not yet Resolved')
            ->assertSee('Close Ticket');
    }

    public function test_status_filters_use_the_new_statuses(): void
    {
        $this->ticket(Ticket::STATUS_ASSIGNED)->complaint->update(['subject_title' => 'Waiting on the office']);
        $this->ticket(Ticket::STATUS_IN_PROGRESS)->complaint->update(['subject_title' => 'Being worked on']);

        $this->actingAs($this->student)->get(route('student.complaints.index', ['status' => 'assigned']))
            ->assertOk()
            ->assertSee('Waiting on the office')
            ->assertDontSee('Being worked on');

        $this->actingAs($this->handler)->get(route('recipient.tickets.index', ['status_filter' => 'in_progress']))
            ->assertOk()
            ->assertSee('Being worked on')
            ->assertDontSee('Waiting on the office');
    }
}
