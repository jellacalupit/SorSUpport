<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Complaint;
use App\Models\ComplaintCategory;
use App\Models\Recipient;
use App\Models\Student;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TicketUnreadBadgeTest extends TestCase
{
    use RefreshDatabase;

    public function test_recipient_badges_sum_unread_changes_and_clear_after_opening_a_ticket(): void
    {
        [$recipientUser, $studentUser] = $this->createRecipientAndStudent();
        $firstComplaint = $this->createAssignedTicket($studentUser, $recipientUser, 'First ticket');
        $secondComplaint = $this->createAssignedTicket($studentUser, $recipientUser, 'Second ticket');

        $this->addOtherUserChanges($firstComplaint->ticket, $studentUser, 5);
        $this->addOtherUserChanges($secondComplaint->ticket, $studentUser, 4);

        $this->actingAs($recipientUser)
            ->get(route('recipient.tickets.index'))
            ->assertOk()
            ->assertSee('>5<', false)
            ->assertSee('>4<', false)
            ->assertSee('>9<', false);

        $this->actingAs($recipientUser)
            ->get(route('recipient.notifications'))
            ->assertOk()
            ->assertSee('9 unread')
            ->assertSee('>9<', false);

        $this->actingAs($recipientUser)
            ->get(route('recipient.tickets.show', $firstComplaint))
            ->assertOk();

        $this->actingAs($recipientUser)
            ->get(route('recipient.tickets.index'))
            ->assertOk()
            ->assertDontSee('>5<', false)
            ->assertSee('>4<', false)
            ->assertDontSee('>9<', false);

        $this->actingAs($recipientUser)
            ->get(route('recipient.notifications'))
            ->assertOk()
            ->assertSee('4 unread')
            ->assertDontSee('9 unread');
    }

    public function test_opening_one_notification_marks_it_read_but_keeps_it_visible(): void
    {
        [$recipientUser, $studentUser] = $this->createRecipientAndStudent();
        $complaint = $this->createAssignedTicket($studentUser, $recipientUser, 'Notification ticket');
        $this->addOtherUserChanges($complaint->ticket, $studentUser, 3);

        $log = $complaint->ticket->auditLogs()->where('action', 'message_posted')->firstOrFail();

        $this->actingAs($recipientUser)
            ->post(route('recipient.notifications.open', "audit-{$log->id}"))
            ->assertRedirect(route('recipient.tickets.show', $complaint));

        $this->actingAs($recipientUser)
            ->get(route('recipient.tickets.index'))
            ->assertOk()
            ->assertDontSee('>3<', false);

        $this->actingAs($recipientUser)
            ->get(route('recipient.notifications'))
            ->assertOk()
            ->assertSee('0 unread')
            ->assertSee('posted a reply message');
    }

    public function test_viewing_a_ticket_keeps_notification_visible_until_deleted(): void
    {
        [$recipientUser, $studentUser] = $this->createRecipientAndStudent();
        $complaint = $this->createAssignedTicket($studentUser, $recipientUser, 'Visible notification ticket');
        $this->addOtherUserChanges($complaint->ticket, $studentUser, 2);

        $this->actingAs($recipientUser)
            ->get(route('recipient.tickets.show', $complaint))
            ->assertOk();

        $this->actingAs($recipientUser)
            ->get(route('recipient.notifications'))
            ->assertOk()
            ->assertSee('0 unread')
            ->assertSee('posted a reply message');
    }

    public function test_student_badges_match_other_user_changes_and_clear_after_opening_ticket(): void
    {
        [$recipientUser, $studentUser] = $this->createRecipientAndStudent();
        $firstComplaint = $this->createAssignedTicket($studentUser, $recipientUser, 'Student first');
        $secondComplaint = $this->createAssignedTicket($studentUser, $recipientUser, 'Student second');

        $this->addOtherUserChanges($firstComplaint->ticket, $recipientUser, 5);
        $this->addOtherUserChanges($secondComplaint->ticket, $recipientUser, 4);

        $this->actingAs($studentUser)
            ->get(route('student.complaints.index'))
            ->assertOk()
            ->assertSee('>5<', false)
            ->assertSee('>4<', false)
            ->assertSee('>9<', false);

        $this->actingAs($studentUser)
            ->get(route('student.complaints.show', $firstComplaint))
            ->assertOk();

        $this->actingAs($studentUser)
            ->get(route('student.dashboard'))
            ->assertOk()
            ->assertDontSee('>5<', false)
            ->assertSee('>4<', false);

        $this->actingAs($studentUser)
            ->get(route('student.notifications'))
            ->assertOk()
            ->assertSee('4 unread');
    }

    public function test_student_submission_stays_unread_until_the_ticket_is_opened(): void
    {
        [$recipientUser, $studentUser] = $this->createRecipientAndStudent();
        $complaint = $this->createAssignedTicket($studentUser, $recipientUser, 'Submission notification');

        AuditLog::log($complaint->ticket->id, 'complaint_submitted', $studentUser->id, 'Complaint submitted.');

        $this->actingAs($studentUser)
            ->get(route('student.complaints.index'))
            ->assertOk()
            ->assertSee('>1<', false);

        $this->actingAs($studentUser)
            ->get(route('student.complaints.show', $complaint))
            ->assertOk();

        $this->actingAs($studentUser)
            ->get(route('student.notifications'))
            ->assertOk()
            ->assertSee('0 unread');
    }

    public function test_deleting_a_notification_removes_it_from_the_list(): void
    {
        [$recipientUser, $studentUser] = $this->createRecipientAndStudent();
        $complaint = $this->createAssignedTicket($studentUser, $recipientUser, 'Delete notification ticket');
        $this->addOtherUserChanges($complaint->ticket, $studentUser, 1);

        $log = $complaint->ticket->auditLogs()->where('action', 'message_posted')->firstOrFail();

        $this->actingAs($recipientUser)
            ->post(route('recipient.notifications.delete-selected'), [
                'notification_ids' => ["audit-{$log->id}"],
            ])
            ->assertRedirect();

        $this->actingAs($recipientUser)
            ->get(route('recipient.notifications'))
            ->assertOk()
            ->assertDontSee('New message')
            ->assertDontSee('Posted a reply message.');
    }

    /**
     * @return array{0: User, 1: User}
     */
    private function createRecipientAndStudent(): array
    {
        $studentUser = User::factory()->create([
            'role' => User::ROLE_STUDENT,
            'email_verified_at' => now(),
            'must_change_password' => false,
        ]);

        Student::create([
            'user_id' => $studentUser->id,
            'student_id' => 'S9001',
            'department' => 'IT',
            'course' => 'BSIT',
            'year_level' => '2nd Year',
            'block' => 'A',
        ]);

        $recipientUser = User::factory()->create([
            'role' => User::ROLE_RECIPIENT,
            'email_verified_at' => now(),
            'must_change_password' => false,
        ]);

        Recipient::create([
            'user_id' => $recipientUser->id,
            'staff_id' => 'R9001',
            'department' => 'Student Affairs',
            'designation' => 'Officer',
        ]);

        return [$recipientUser, $studentUser];
    }

    private function createAssignedTicket(User $studentUser, User $recipientUser, string $title): Complaint
    {
        $category = ComplaintCategory::query()->first() ?? ComplaintCategory::create([
            'name' => 'Academic Concern',
            'resolution_deadline_days' => 3,
            'is_active' => true,
        ]);

        $complaint = Complaint::create([
            'reference_number' => Complaint::generateReferenceNumber(),
            'student_id' => $studentUser->student->id,
            'category_id' => $category->id,
            'subject_title' => $title,
            'description' => 'Unread badge coverage.',
            'is_anonymous' => false,
            'status' => Complaint::STATUS_PENDING,
        ]);

        Ticket::create([
            'complaint_id' => $complaint->id,
            'status' => Ticket::STATUS_ASSIGNED,
            'assigned_to' => $recipientUser->id,
        ]);

        return $complaint->fresh(['ticket']);
    }

    private function addOtherUserChanges(Ticket $ticket, User $actor, int $count): void
    {
        $thread = $ticket->thread()->first() ?? $ticket->thread()->create(['is_active' => true]);

        for ($i = 0; $i < $count; $i++) {
            $thread->messages()->create([
                'sender_id' => $actor->id,
                'content' => "Change {$i}",
            ]);
            AuditLog::log($ticket->id, 'message_posted', $actor->id, 'Posted a reply message.');
        }
    }
}
