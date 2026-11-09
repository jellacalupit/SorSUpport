<?php

namespace Tests\Feature;

use App\Models\Complaint;
use App\Models\ComplaintCategory;
use App\Models\EmailNotification;
use App\Models\Recipient;
use App\Models\Student;
use App\Models\Ticket;
use App\Models\User;
use App\Services\TicketEmailComposer;
use App\Services\TicketUnreadService;
use App\Services\TicketWorkflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RecipientReturnToSdsTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected User $secondAdmin;

    protected User $student;

    protected User $handler;

    protected User $otherRecipient;

    protected ComplaintCategory $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = $this->user(User::ROLE_SDS_ADMIN, 'Ada Admin');
        $this->secondAdmin = $this->user(User::ROLE_SDS_ADMIN, 'Bea Admin');
        $this->student = $this->user(User::ROLE_STUDENT, 'Sam Student');
        $this->handler = $this->user(User::ROLE_RECIPIENT, 'Rey Registrar');
        $this->otherRecipient = $this->user(User::ROLE_RECIPIENT, 'Dana Dean');

        Student::create([
            'user_id' => $this->student->id,
            'student_id' => 'STUDENT-PRIVATE-42',
            'college' => 'CICT',
            'program' => 'BSIT',
            'year_level' => '1',
            'block' => '1',
        ]);

        foreach ([$this->handler, $this->otherRecipient] as $index => $user) {
            Recipient::create([
                'user_id' => $user->id,
                'staff_id' => 'STAFF-' . $index,
                'unit' => 'Registrar',
                'designation' => 'Staff',
            ]);
        }

        $this->category = ComplaintCategory::create(['name' => 'Student Services', 'is_active' => true]);
    }

    public function test_current_recipient_can_return_assigned_in_progress_and_escalated_tickets_to_sds(): void
    {
        foreach ([Ticket::STATUS_ASSIGNED, Ticket::STATUS_IN_PROGRESS, Ticket::STATUS_ESCALATED] as $status) {
            $ticket = $this->ticket($status);
            $reason = "This ticket belongs with SDS from {$status}.";

            $this->actingAs($this->handler)
                ->post(route('recipient.complaints.return-to-sds', $ticket->complaint), ['return_reason' => $reason])
                ->assertRedirect(route('recipient.tickets.index'));

            $ticket->refresh();
            $this->assertSame(Ticket::STATUS_ASSIGNED, $ticket->status);
            $this->assertSame($this->admin->id, $ticket->current_handler_id);
            $this->assertNull($ticket->assigned_to);
            $this->assertNull($ticket->acknowledged_at);
            $this->assertDatabaseHas('audit_logs', [
                'ticket_id' => $ticket->id,
                'performed_by' => $this->handler->id,
                'action' => 'returned_to_sds',
                'details' => $reason,
            ]);

            $this->assertDatabaseHas('email_notifications', [
                'ticket_id' => $ticket->id,
                'recipient_email' => $this->admin->email,
                'type' => EmailNotification::TYPE_RETURNED_TO_SDS,
                'status' => EmailNotification::STATUS_PENDING,
            ]);
            $this->assertDatabaseHas('email_notifications', [
                'ticket_id' => $ticket->id,
                'recipient_email' => $this->secondAdmin->email,
                'type' => EmailNotification::TYPE_RETURNED_TO_SDS,
                'status' => EmailNotification::STATUS_PENDING,
            ]);
            $this->assertDatabaseMissing('email_notifications', [
                'ticket_id' => $ticket->id,
                'recipient_email' => $this->student->email,
            ]);

            $this->assertSame(1, app(TicketUnreadService::class)->unreadCountForTicket($this->admin, $ticket));

            $this->actingAs($this->admin)
                ->get(route('admin.complaints.show', $ticket->complaint))
                ->assertOk()
                ->assertSee('Returned by office')
                ->assertSee($reason);
            $this->actingAs($this->admin)
                ->get(route('admin.tickets.needs-attention'))
                ->assertOk()
                ->assertSee('Returned by office');

            $this->actingAs($this->student)
                ->get(route('student.complaints.show', $ticket->complaint))
                ->assertOk()
                ->assertDontSee($reason);

            $this->actingAs($this->handler)
                ->get(route('recipient.complaints.show', $ticket->complaint))
                ->assertForbidden();
        }
    }

    public function test_a_returned_ticket_stays_in_needs_attention_until_sds_acts(): void
    {
        $ticket = $this->ticket(Ticket::STATUS_ASSIGNED);

        $this->actingAs($this->handler)
            ->post(route('recipient.complaints.return-to-sds', $ticket->complaint), [
                'return_reason' => 'This ticket was routed to the wrong office.',
            ])
            ->assertRedirect();

        $this->assertNotNull(app(TicketWorkflow::class)->pendingReturnToSds($ticket->fresh()));

        $this->actingAs($this->admin)
            ->post(route('admin.tickets.assign', $ticket), ['assignment_mode' => 'direct'])
            ->assertRedirect();

        $this->assertNull(app(TicketWorkflow::class)->pendingReturnToSds($ticket->fresh()));
        $this->actingAs($this->admin)
            ->get(route('admin.tickets.needs-attention'))
            ->assertOk()
            ->assertDontSee('Returned by office');
    }

    public function test_return_reason_is_required_and_limited_to_10_through_500_characters(): void
    {
        $ticket = $this->ticket(Ticket::STATUS_ASSIGNED);
        $url = route('recipient.complaints.return-to-sds', $ticket->complaint);

        foreach ([[], ['return_reason' => 'Too short'], ['return_reason' => str_repeat('x', 501)]] as $payload) {
            $this->actingAs($this->handler)
                ->from(route('recipient.complaints.show', $ticket->complaint))
                ->post($url, $payload)
                ->assertSessionHasErrors('return_reason');
        }

        $this->assertSame(Ticket::STATUS_ASSIGNED, $ticket->fresh()->status);
        $this->assertDatabaseMissing('audit_logs', ['ticket_id' => $ticket->id, 'action' => 'returned_to_sds']);
    }

    public function test_only_the_current_recipient_can_return_a_ticket_and_the_button_is_status_limited(): void
    {
        $ticket = $this->ticket(Ticket::STATUS_ASSIGNED);

        $this->actingAs($this->otherRecipient)
            ->post(route('recipient.complaints.return-to-sds', $ticket->complaint), [
                'return_reason' => 'This recipient is not assigned.',
            ])
            ->assertForbidden();
        $this->actingAs($this->student)
            ->post(route('recipient.complaints.return-to-sds', $ticket->complaint), [
                'return_reason' => 'A student cannot return this ticket.',
            ])
            ->assertForbidden();

        foreach (Ticket::STATUS_LABELS as $status => $label) {
            $statusTicket = $this->ticket($status);
            $page = $this->actingAs($this->handler)->get(route('recipient.complaints.show', $statusTicket->complaint));

            if (in_array($status, [Ticket::STATUS_ASSIGNED, Ticket::STATUS_IN_PROGRESS, Ticket::STATUS_ESCALATED], true)) {
                $page->assertOk()->assertSee('Return to SDS');
            } else {
                $page->assertOk()->assertDontSee('Return to SDS');
            }
        }
    }

    public function test_return_notifications_do_not_identify_hidden_or_sensitive_tickets(): void
    {
        $sensitiveCategory = ComplaintCategory::create([
            'name' => 'Sensitive concern',
            'is_active' => true,
            'is_sensitive' => true,
            'allows_hidden_identity' => true,
        ]);

        foreach ([
            [
                'category_id' => $this->category->id,
                'is_anonymous' => true,
                'subject_title' => 'Hidden identity subject',
            ],
            [
                'category_id' => $sensitiveCategory->id,
                'is_anonymous' => false,
                'subject_title' => 'Sensitive subject should stay private',
            ],
        ] as $complaintData) {
            $ticket = $this->ticket(Ticket::STATUS_ESCALATED, [], $complaintData);
            $reason = 'Wrong office for this confidential matter.';

            $this->actingAs($this->handler)
                ->post(route('recipient.complaints.return-to-sds', $ticket->complaint), ['return_reason' => $reason])
                ->assertRedirect();

            $notification = EmailNotification::query()
                ->where('ticket_id', $ticket->id)
                ->where('recipient_email', $this->admin->email)
                ->where('type', EmailNotification::TYPE_RETURNED_TO_SDS)
                ->firstOrFail();
            $email = app(TicketEmailComposer::class)->compose($notification);
            $rendered = view('emails.notifications.layout', $email)->render();
            $emailText = html_entity_decode(strip_tags($rendered), ENT_QUOTES);

            $this->assertSame('Ticket returned to SDS', $email['subject']);
            $this->assertSame([], $email['details']);
            foreach ([
                $ticket->complaint->reference_number,
                $ticket->complaint->subject_title,
                $this->student->name,
                'STUDENT-PRIVATE-42',
                $reason,
            ] as $privateText) {
                $this->assertStringNotContainsString($privateText, $emailText);
                $this->assertStringNotContainsString($privateText, $email['subject']);
            }

            $this->actingAs($this->student)
                ->get(route('student.complaints.show', $ticket->complaint))
                ->assertOk()
                ->assertDontSee($reason);
            $this->assertSame(0, app(TicketUnreadService::class)->unreadCountForTicket($this->student, $ticket));
        }
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

    protected function ticket(string $status, array $attributes = [], array $complaintData = []): Ticket
    {
        $complaint = Complaint::create($complaintData + [
            'reference_number' => Complaint::generateReferenceNumber(),
            'student_id' => $this->student->student->id,
            'category_id' => $this->category->id,
            'subject_title' => 'Transcript request',
            'description' => 'My transcript has not been released.',
            'is_anonymous' => false,
            'status' => $status,
        ]);

        return Ticket::create($attributes + [
            'complaint_id' => $complaint->id,
            'status' => $status,
            'classification' => Ticket::CLASSIFICATION_NEEDS_RESOLUTION,
            'jurisdiction' => Ticket::JURISDICTION_RECIPIENT,
            'assigned_to' => $this->handler->id,
            'current_handler_id' => $this->handler->id,
        ]);
    }
}
