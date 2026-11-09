<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Complaint;
use App\Models\ComplaintCategory;
use App\Models\Recipient;
use App\Models\Student;
use App\Models\Ticket;
use App\Models\User;
use App\Services\TicketReminderService;
use App\Services\TicketWorkflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ForwardedInformationalTicketTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected User $student;

    protected User $recipient;

    protected ComplaintCategory $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = $this->user(User::ROLE_SDS_ADMIN, 'Ada Admin');
        $this->student = $this->user(User::ROLE_STUDENT, 'Sam Student');
        $this->recipient = $this->user(User::ROLE_RECIPIENT, 'Rey Registrar');

        Student::create([
            'user_id' => $this->student->id,
            'student_id' => 'STUDENT-2026-1',
            'college' => 'CICT',
            'program' => 'BSIT',
            'year_level' => '1',
            'block' => '1',
        ]);

        Recipient::create([
            'user_id' => $this->recipient->id,
            'staff_id' => 'STAFF-1',
            'unit' => 'Registrar Office',
            'designation' => 'Staff',
        ]);

        $this->category = ComplaintCategory::create(['name' => 'Student Services', 'is_active' => true]);
        $this->category->suggestedRecipients()->attach($this->recipient->recipient->id);
    }

    public function test_forwarded_informational_ticket_keeps_submitted_storage_but_shows_forward_details_to_student_and_recipient(): void
    {
        $ticket = $this->ticket(Ticket::STATUS_SUBMITTED, [
            'classification' => Ticket::CLASSIFICATION_INFORMATIONAL,
            'jurisdiction' => Ticket::JURISDICTION_RECIPIENT,
        ]);

        $this->actingAs($this->admin)
            ->post(route('admin.tickets.forward', $ticket), ['recipient_id' => $this->recipient->recipient->id])
            ->assertRedirect(route('admin.tickets.review.index'));

        $ticket->refresh();
        $forwardedDate = $ticket->forwarded_at->copy()->setTimezone('Asia/Manila')->format('M d, Y');
        $this->assertSame(Ticket::STATUS_SUBMITTED, $ticket->status);
        $this->assertSame(Ticket::CLASSIFICATION_INFORMATIONAL, $ticket->classification);
        $this->assertSame(Ticket::JURISDICTION_RECIPIENT, $ticket->jurisdiction);
        $this->assertSame($this->recipient->recipient->id, (int) $ticket->forwarded_to);
        $this->assertNotNull($ticket->forwarded_at);
        $this->assertNull($ticket->closed_at);
        $this->assertTrue(app(TicketWorkflow::class)->can($this->admin, $ticket, TicketWorkflow::CLOSE));
        $this->assertFalse(app(TicketWorkflow::class)->can($this->recipient, $ticket, TicketWorkflow::CLOSE));

        $this->actingAs($this->student)
            ->get(route('student.complaints.show', $ticket->complaint))
            ->assertOk()
            ->assertSee('Forwarded for information')
            ->assertSee('Registrar Office')
            ->assertSee($forwardedDate);

        $this->actingAs($this->recipient)
            ->get(route('recipient.complaints.show', $ticket->complaint))
            ->assertOk()
            ->assertSee('Forwarded for information')
            ->assertSee('Registrar Office')
            ->assertSee($forwardedDate)
            ->assertSee('For your information. No reply or action is needed.');

        $this->actingAs($this->recipient)
            ->get(route('recipient.tickets.index'))
            ->assertOk()
            ->assertSee('Forwarded for information')
            ->assertSee('Registrar Office')
            ->assertSee($forwardedDate);

        $this->actingAs($this->student)
            ->get(route('student.complaints.index', ['status' => Ticket::STATUS_SUBMITTED]))
            ->assertOk()
            ->assertSee($ticket->complaint->reference_number);
    }

    public function test_unforwarded_informational_and_retained_tickets_keep_their_existing_display(): void
    {
        $unforwarded = $this->ticket(Ticket::STATUS_SUBMITTED, [
            'classification' => Ticket::CLASSIFICATION_INFORMATIONAL,
            'jurisdiction' => Ticket::JURISDICTION_RECIPIENT,
        ]);
        $this->assertFalse($unforwarded->isForwardedInformational());
        $this->actingAs($this->student)
            ->get(route('student.complaints.show', $unforwarded->complaint))
            ->assertOk()
            ->assertSee('Submitted')
            ->assertDontSee('Forwarded for information');

        $retained = $this->ticket(Ticket::STATUS_SUBMITTED);
        $this->actingAs($this->admin)
            ->post(route('admin.tickets.retain-informational', $retained))
            ->assertRedirect(route('admin.tickets.review.index'));

        $retained->refresh();
        $this->assertSame(Ticket::STATUS_CLOSED, $retained->status);
        $this->assertSame(Ticket::CLASSIFICATION_INFORMATIONAL, $retained->classification);
        $this->assertFalse($retained->isForwardedInformational());
        $this->actingAs($this->student)
            ->get(route('student.complaints.show', $retained->complaint))
            ->assertOk()
            ->assertSee('Closed')
            ->assertDontSee('Forwarded for information');
    }

    public function test_forwarded_informational_ticket_is_not_in_review_queue_or_submitted_reminders(): void
    {
        $ticket = $this->ticket(Ticket::STATUS_SUBMITTED, [
            'classification' => Ticket::CLASSIFICATION_INFORMATIONAL,
            'jurisdiction' => Ticket::JURISDICTION_RECIPIENT,
        ]);

        $this->actingAs($this->admin)
            ->post(route('admin.tickets.forward', $ticket), ['recipient_id' => $this->recipient->recipient->id])
            ->assertRedirect();

        AuditLog::query()
            ->where('ticket_id', $ticket->id)
            ->where('action', 'ticket_forwarded_to_recipient')
            ->update(['created_at' => now()->subDays(5)]);
        Ticket::query()->whereKey($ticket->id)->update(['updated_at' => now()->subDays(5)]);

        $this->actingAs($this->admin)
            ->get(route('admin.tickets.review.index'))
            ->assertOk()
            ->assertDontSee($ticket->complaint->reference_number);

        $this->assertNull(app(TicketReminderService::class)->allowedDays($ticket->fresh()));
        $this->assertFalse(app(TicketReminderService::class)->isDue($ticket->fresh()));
        $this->assertSame(0, app(TicketReminderService::class)->queueReminders());
        $this->assertDatabaseMissing('email_notifications', [
            'ticket_id' => $ticket->id,
            'type' => \App\Models\EmailNotification::TYPE_WAITING_REMINDER,
        ]);
    }

    public function test_forwarded_display_preserves_hidden_identity_and_sensitive_subject_privacy(): void
    {
        $sensitiveCategory = ComplaintCategory::create([
            'name' => 'Sensitive concern',
            'is_active' => true,
            'is_sensitive' => true,
            'allows_hidden_identity' => true,
        ]);
        $ticket = $this->ticket(Ticket::STATUS_SUBMITTED, [
            'classification' => Ticket::CLASSIFICATION_INFORMATIONAL,
            'jurisdiction' => Ticket::JURISDICTION_RECIPIENT,
        ], [
            'category_id' => $sensitiveCategory->id,
            'subject_title' => 'PRIVATE-SENSITIVE-SUBJECT',
            'is_anonymous' => true,
        ]);

        $this->actingAs($this->admin)
            ->post(route('admin.tickets.forward', $ticket), ['recipient_id' => $this->recipient->recipient->id])
            ->assertRedirect();

        $this->actingAs($this->recipient)
            ->get(route('recipient.complaints.show', $ticket->complaint))
            ->assertOk()
            ->assertSee('Forwarded for information')
            ->assertSee('Confidential concern')
            ->assertDontSee('PRIVATE-SENSITIVE-SUBJECT')
            ->assertDontSee($this->student->name)
            ->assertDontSee('STUDENT-2026-1');

        $this->actingAs($this->admin)
            ->get(route('admin.complaints.show', $ticket->complaint))
            ->assertOk()
            ->assertSee('Anonymous')
            ->assertSee('Confidential concern')
            ->assertDontSee('PRIVATE-SENSITIVE-SUBJECT')
            ->assertDontSee($this->student->name)
            ->assertDontSee('STUDENT-2026-1');
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

    protected function ticket(string $status, array $attributes = [], array $complaintAttributes = []): Ticket
    {
        $complaint = Complaint::create($complaintAttributes + [
            'reference_number' => Complaint::generateReferenceNumber(),
            'student_id' => $this->student->student->id,
            'category_id' => $this->category->id,
            'subject_title' => 'Transcript request',
            'description' => 'A request for information.',
            'is_anonymous' => false,
            'status' => $status,
        ]);

        return Ticket::create($attributes + [
            'complaint_id' => $complaint->id,
            'status' => $status,
            'classification' => null,
            'jurisdiction' => null,
            'assigned_to' => null,
            'current_handler_id' => $this->admin->id,
        ]);
    }
}
