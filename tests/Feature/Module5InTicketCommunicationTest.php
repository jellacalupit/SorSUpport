<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Complaint;
use App\Models\ComplaintCategory;
use App\Models\Recipient;
use App\Models\Student;
use App\Models\ThreadMessage;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class Module5InTicketCommunicationTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_complaints_index_handles_complaints_without_tickets(): void
    {
        $admin = User::factory()->create([
            'role' => User::ROLE_SDS_ADMIN,
            'email_verified_at' => now(),
            'must_change_password' => false,
        ]);
        $studentUser = User::factory()->create([
            'role' => User::ROLE_STUDENT,
            'email_verified_at' => now(),
            'must_change_password' => false,
        ]);
        $student = Student::create([
            'user_id' => $studentUser->id,
            'student_id' => 'S9001',
            'department' => 'IT',
            'course' => 'BSIT',
            'year_level' => '2nd Year',
            'block' => 'A',
        ]);
        $category = ComplaintCategory::create([
            'name' => 'Missing Ticket Category',
            'resolution_deadline_days' => 3,
            'is_active' => true,
        ]);
        $complaint = Complaint::create([
            'reference_number' => Complaint::generateReferenceNumber(),
            'student_id' => $student->id,
            'category_id' => $category->id,
            'subject_title' => 'Complaint without a ticket',
            'description' => 'The admin list should still render this complaint.',
            'is_anonymous' => false,
            'status' => Complaint::STATUS_PENDING,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.complaints.index'))
            ->assertOk()
            ->assertSee($complaint->subject_title);

        // Tickets open as their own page, which is where the missing ticket is explained.
        $this->actingAs($admin)
            ->get(route('admin.complaints.show', $complaint))
            ->assertOk()
            ->assertSee('No ticket has been generated for this complaint yet.');
    }

    public function test_student_recipient_and_admin_can_post_and_see_messages_in_same_thread(): void
    {
        // Create test users
        /** @var User $admin */
        $admin = User::factory()->create([
            'role' => User::ROLE_SDS_ADMIN,
            'email_verified_at' => now(),
            'must_change_password' => false,
        ]);

        /** @var User $studentUser */
        $studentUser = User::factory()->create([
            'role' => User::ROLE_STUDENT,
            'email_verified_at' => now(),
            'must_change_password' => false,
        ]);

        $studentProfile = Student::create([
            'user_id' => $studentUser->id,
            'student_id' => 'S2001',
            'department' => 'IT',
            'course' => 'BSIT',
            'year_level' => '2nd Year',
            'block' => 'A',
        ]);

        /** @var User $recipientUser */
        $recipientUser = User::factory()->create([
            'role' => User::ROLE_RECIPIENT,
            'email_verified_at' => now(),
            'must_change_password' => false,
        ]);

        $recipient = Recipient::create([
            'user_id' => $recipientUser->id,
            'staff_id' => 'R1001',
            'department' => 'Student Affairs',
            'designation' => 'Officer',
        ]);

        $category = ComplaintCategory::create([
            'name' => 'Academic Concern',
            'resolution_deadline_days' => 3,
            'is_active' => true,
        ]);

        $complaint = Complaint::create([
            'reference_number' => Complaint::generateReferenceNumber(),
            'student_id' => $studentProfile->id,
            'category_id' => $category->id,
            'subject_title' => 'Issue with course',
            'description' => 'I have an issue',
            'is_anonymous' => false,
            'status' => Complaint::STATUS_PENDING,
        ]);

        $ticket = Ticket::create([
            'complaint_id' => $complaint->id,
            'status' => Ticket::STATUS_PENDING,
        ]);

        // Classify as Needs Resolution
        $this->actingAs($admin)
            ->post(route('admin.tickets.classify', $ticket), [
                'classification' => Ticket::CLASSIFICATION_NEEDS_RESOLUTION,
                'jurisdiction' => Ticket::JURISDICTION_RECIPIENT,
            ]);

        // Assign to recipient
        $this->actingAs($admin)
            ->post(route('admin.tickets.assign', $ticket), [
                'assignment_mode' => 'recipient',
                'recipient_id' => $recipient->id,
            ]);

        $ticket->refresh();

        // ===== STUDENT POSTS MESSAGE =====
        $studentReply = $this->actingAs($studentUser)
            ->post(route('student.complaints.reply', $complaint), [
                'content' => 'This is my initial message',
            ]);
        Log::debug('TEST: studentReply status', ['status' => $studentReply->getStatusCode(), 'headers' => $studentReply->headers->all()]);
        $studentReply->assertRedirect();

        // ===== ADMIN POSTS MESSAGE =====
        $adminReply = $this->actingAs($admin)
            ->post(route('admin.complaints.reply', $complaint), [
                'content' => 'Admin response to student',
            ]);
        Log::debug('TEST: adminReply status', ['status' => $adminReply->getStatusCode(), 'headers' => $adminReply->headers->all()]);
        $adminReply->assertRedirect();

        // ===== RECIPIENT POSTS MESSAGE =====
        $recipientReply = $this->actingAs($recipientUser)
            ->post(route('recipient.complaints.reply', $complaint), [
                'content' => 'Recipient response',
            ]);
        Log::debug('TEST: recipientReply status', ['status' => $recipientReply->getStatusCode(), 'headers' => $recipientReply->headers->all()]);
        $recipientReply->assertRedirect();

        // ===== VERIFY ALL THREE CAN SEE ALL MESSAGES =====
        // Student views
        $studentShow = $this->actingAs($studentUser)
            ->get(route('student.complaints.show', $complaint))
            ->assertOk()
            ->assertSee('This is my initial message')
            ->assertSee('Admin response to student')
            ->assertSee('Recipient response');

        // Recipient views
        $complaint->refresh();
        $recipientShow = $this->actingAs($recipientUser)
            ->get(route('recipient.complaints.show', $complaint))
            ->assertOk()
            ->assertSee('This is my initial message')
            ->assertSee('Admin response to student')
            ->assertSee('Recipient response');

        // Admin views
        $complaint->refresh();
        $adminShow = $this->actingAs($admin)
            ->get(route('admin.complaints.show', $complaint))
            ->assertOk()
            ->assertSee('This is my initial message')
            ->assertSee('Admin response to student')
            ->assertSee('Recipient response');

        // Verify 3 messages in database
        $this->assertDatabaseCount('thread_messages', 3);
        $this->assertDatabaseHas('thread_messages', [
            'content' => 'This is my initial message',
            'sender_id' => $studentUser->id,
        ]);
        $this->assertDatabaseHas('thread_messages', [
            'content' => 'Admin response to student',
            'sender_id' => $admin->id,
        ]);
        $this->assertDatabaseHas('thread_messages', [
            'content' => 'Recipient response',
            'sender_id' => $recipientUser->id,
        ]);
    }

    public function test_ticket_deadline_starts_on_submission_for_15_day_policy(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 8, 29, 12, 0, 0, 'Asia/Manila'));

        $studentUser = User::factory()->create([
            'role' => User::ROLE_STUDENT,
            'email_verified_at' => now(),
            'must_change_password' => false,
        ]);

        $studentProfile = Student::create([
            'user_id' => $studentUser->id,
            'student_id' => 'S2030',
            'department' => 'IT',
            'course' => 'BSIT',
            'year_level' => '2nd Year',
            'block' => 'A',
        ]);

        $category = ComplaintCategory::create([
            'name' => 'Academic Concern',
            'resolution_deadline_days' => 15,
            'is_active' => true,
        ]);

        $complaint = Complaint::create([
            'reference_number' => Complaint::generateReferenceNumber(),
            'student_id' => $studentProfile->id,
            'category_id' => $category->id,
            'subject_title' => 'Fifteen day policy check',
            'description' => 'Deadline should start at submission.',
            'is_anonymous' => false,
            'status' => Complaint::STATUS_PENDING,
        ]);

        $ticket = Ticket::create([
            'complaint_id' => $complaint->id,
            'status' => Ticket::STATUS_PENDING,
            'deadline' => now()->addDays(15),
        ]);

        $this->assertNotNull($ticket->deadline);
        $this->assertSame(15, $ticket->remainingDays());
        $this->assertSame(
            now()->copy()->addDays(15)->startOfDay()->format('Y-m-d'),
            $ticket->deadline->copy()->startOfDay()->format('Y-m-d')
        );

        Carbon::setTestNow();
    }

    public function test_ticket_card_badge_counts_other_user_messages(): void
    {
        $studentUser = User::factory()->create([
            'role' => User::ROLE_STUDENT,
            'email_verified_at' => now(),
            'must_change_password' => false,
        ]);

        $studentProfile = Student::create([
            'user_id' => $studentUser->id,
            'student_id' => 'S2004',
            'department' => 'IT',
            'course' => 'BSIT',
            'year_level' => '2nd Year',
            'block' => 'A',
        ]);

        $otherUser = User::factory()->create([
            'role' => User::ROLE_RECIPIENT,
            'email_verified_at' => now(),
            'must_change_password' => false,
        ]);

        $category = ComplaintCategory::create([
            'name' => 'Academic Concern',
            'resolution_deadline_days' => 3,
            'is_active' => true,
        ]);

        $complaint = Complaint::create([
            'reference_number' => Complaint::generateReferenceNumber(),
            'student_id' => $studentProfile->id,
            'category_id' => $category->id,
            'subject_title' => 'Unread activity count',
            'description' => 'Should show five new messages from others.',
            'is_anonymous' => false,
            'status' => Complaint::STATUS_PENDING,
        ]);

        $ticket = Ticket::create([
            'complaint_id' => $complaint->id,
            'status' => Ticket::STATUS_PENDING,
        ]);

        $thread = $ticket->thread()->create(['is_active' => true]);

        for ($i = 0; $i < 5; $i++) {
            ThreadMessage::create([
                'thread_id' => $thread->id,
                'sender_id' => $otherUser->id,
                'content' => "Message {$i}",
            ]);
            AuditLog::log($ticket->id, 'message_posted', $otherUser->id, "Recipient posted a reply message.");
        }

        $this->actingAs($studentUser)
            ->get(route('student.complaints.index'))
            ->assertOk()
            ->assertSee('5');
    }

    public function test_recipient_ticket_badge_clears_when_ticket_is_explicitly_marked_read(): void
    {
        $studentUser = User::factory()->create([
            'role' => User::ROLE_STUDENT,
            'email_verified_at' => now(),
            'must_change_password' => false,
        ]);

        $studentProfile = Student::create([
            'user_id' => $studentUser->id,
            'student_id' => 'S2007',
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

        $recipient = Recipient::create([
            'user_id' => $recipientUser->id,
            'staff_id' => 'R1007',
            'department' => 'Student Affairs',
            'designation' => 'Officer',
        ]);

        $category = ComplaintCategory::create([
            'name' => 'Academic Concern',
            'resolution_deadline_days' => 3,
            'is_active' => true,
        ]);

        $complaint = Complaint::create([
            'reference_number' => Complaint::generateReferenceNumber(),
            'student_id' => $studentProfile->id,
            'category_id' => $category->id,
            'subject_title' => 'Explicitly marked read',
            'description' => 'The ticket should stay clear if it was already marked read.',
            'is_anonymous' => false,
            'status' => Complaint::STATUS_PENDING,
        ]);

        $ticket = Ticket::create([
            'complaint_id' => $complaint->id,
            'status' => Ticket::STATUS_PENDING,
            'assigned_to' => $recipientUser->id,
        ]);

        $thread = $ticket->thread()->create(['is_active' => true]);

        ThreadMessage::create([
            'thread_id' => $thread->id,
            'sender_id' => $studentUser->id,
            'content' => 'This message was read previously',
        ]);

        $recipientUser->update([
            'recipient_ticket_read_ids' => [(string) $complaint->id],
            'recipient_ticket_last_read_at' => [],
        ]);

        $this->actingAs($recipientUser)
            ->get(route('recipient.tickets.index'))
            ->assertOk()
            ->assertDontSee('>1<');
    }

    public function test_recipient_ticket_badge_clears_after_opening_ticket(): void
    {
        $studentUser = User::factory()->create([
            'role' => User::ROLE_STUDENT,
            'email_verified_at' => now(),
            'must_change_password' => false,
        ]);

        $studentProfile = Student::create([
            'user_id' => $studentUser->id,
            'student_id' => 'S2006',
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

        $recipient = Recipient::create([
            'user_id' => $recipientUser->id,
            'staff_id' => 'R1006',
            'department' => 'Student Affairs',
            'designation' => 'Officer',
        ]);

        $category = ComplaintCategory::create([
            'name' => 'Academic Concern',
            'resolution_deadline_days' => 3,
            'is_active' => true,
        ]);

        $complaint = Complaint::create([
            'reference_number' => Complaint::generateReferenceNumber(),
            'student_id' => $studentProfile->id,
            'category_id' => $category->id,
            'subject_title' => 'Badge clears after open',
            'description' => 'Should clear once opened.',
            'is_anonymous' => false,
            'status' => Complaint::STATUS_PENDING,
        ]);

        $ticket = Ticket::create([
            'complaint_id' => $complaint->id,
            'status' => Ticket::STATUS_PENDING,
            'assigned_to' => $recipientUser->id,
        ]);

        $thread = $ticket->thread()->create(['is_active' => true]);

        ThreadMessage::create([
            'thread_id' => $thread->id,
            'sender_id' => $studentUser->id,
            'content' => 'Unread message for recipient',
        ]);

        $this->actingAs($recipientUser)
            ->get(route('recipient.tickets.index'))
            ->assertOk()
            ->assertSee('1');

        $this->actingAs($recipientUser)
            ->get(route('recipient.tickets.show', $complaint))
            ->assertOk();

        $this->actingAs($recipientUser)
            ->get(route('recipient.tickets.index'))
            ->assertOk()
            ->assertDontSee('>1<');
    }

    public function test_new_message_updates_ticket_recency(): void
    {
        /** @var User $admin */
        $admin = User::factory()->create([
            'role' => User::ROLE_SDS_ADMIN,
            'email_verified_at' => now(),
            'must_change_password' => false,
        ]);

        /** @var User $studentUser */
        $studentUser = User::factory()->create([
            'role' => User::ROLE_STUDENT,
            'email_verified_at' => now(),
            'must_change_password' => false,
        ]);

        $studentProfile = Student::create([
            'user_id' => $studentUser->id,
            'student_id' => 'S2003',
            'department' => 'IT',
            'course' => 'BSIT',
            'year_level' => '2nd Year',
            'block' => 'A',
        ]);

        $category = ComplaintCategory::create([
            'name' => 'Academic Concern',
            'resolution_deadline_days' => 3,
            'is_active' => true,
        ]);

        $complaint = Complaint::create([
            'reference_number' => Complaint::generateReferenceNumber(),
            'student_id' => $studentProfile->id,
            'category_id' => $category->id,
            'subject_title' => 'Recency test',
            'description' => 'This should update ticket time.',
            'is_anonymous' => false,
            'status' => Complaint::STATUS_PENDING,
        ]);

        $ticket = Ticket::create([
            'complaint_id' => $complaint->id,
            'status' => Ticket::STATUS_PENDING,
            'assigned_to' => null,
        ]);

        $ticket->thread()->create(['is_active' => true]);

        $olderUpdatedAt = Carbon::parse('2026-01-01 08:00:00');
        Carbon::setTestNow($olderUpdatedAt);
        $ticket->touch();
        $before = $ticket->fresh()->updated_at;

        Carbon::setTestNow(Carbon::parse('2026-01-01 08:10:00'));
        $this->actingAs($studentUser)
            ->post(route('student.complaints.reply', $complaint), [
                'content' => 'This message should bump the ticket to the top.',
            ]);

        Carbon::setTestNow();
        $ticket->refresh();

        $this->assertTrue($ticket->updated_at->greaterThan($before));
    }

    public function test_thread_becomes_read_only_after_ticket_closure(): void
    {
        // Create test users
        /** @var User $admin */
        $admin = User::factory()->create([
            'role' => User::ROLE_SDS_ADMIN,
            'email_verified_at' => now(),
            'must_change_password' => false,
        ]);

        /** @var User $studentUser */
        $studentUser = User::factory()->create([
            'role' => User::ROLE_STUDENT,
            'email_verified_at' => now(),
            'must_change_password' => false,
        ]);

        $studentProfile = Student::create([
            'user_id' => $studentUser->id,
            'student_id' => 'S2002',
            'department' => 'IT',
            'course' => 'BSIT',
            'year_level' => '2nd Year',
            'block' => 'A',
        ]);

        /** @var User $recipientUser */
        $recipientUser = User::factory()->create([
            'role' => User::ROLE_RECIPIENT,
            'email_verified_at' => now(),
            'must_change_password' => false,
        ]);

        $recipient = Recipient::create([
            'user_id' => $recipientUser->id,
            'staff_id' => 'R1002',
            'department' => 'Student Affairs',
            'designation' => 'Officer',
        ]);

        $category = ComplaintCategory::create([
            'name' => 'Academic Concern',
            'resolution_deadline_days' => 3,
            'is_active' => true,
        ]);

        $complaint = Complaint::create([
            'reference_number' => Complaint::generateReferenceNumber(),
            'student_id' => $studentProfile->id,
            'category_id' => $category->id,
            'subject_title' => 'Issue with course',
            'description' => 'I have an issue',
            'is_anonymous' => false,
            'status' => Complaint::STATUS_PENDING,
        ]);

        $ticket = Ticket::create([
            'complaint_id' => $complaint->id,
            'status' => Ticket::STATUS_PENDING,
        ]);

        // Classify and assign
        $this->actingAs($admin)
            ->post(route('admin.tickets.classify', $ticket), [
                'classification' => Ticket::CLASSIFICATION_NEEDS_RESOLUTION,
                'jurisdiction' => Ticket::JURISDICTION_RECIPIENT,
            ]);

        $this->actingAs($admin)
            ->post(route('admin.tickets.assign', $ticket), [
                'assignment_mode' => 'recipient',
                'recipient_id' => $recipient->id,
            ]);

        // Send one message before closure
        $this->actingAs($studentUser)
            ->post(route('student.complaints.reply', $complaint), [
                'content' => 'Message before closure',
            ]);

        $this->assertDatabaseCount('thread_messages', 1);

        // Recipient closes the ticket
        $complaint->refresh();
        $this->actingAs($recipientUser)
            ->patch(route('recipient.complaints.update-status', $complaint), [
                'status' => 'resolved',
            ])
            ->assertRedirect();

        // Verify thread is now inactive
        $ticket->refresh();
        $this->assertFalse($ticket->thread->is_active);

        // ===== VERIFY ALL THREE CANNOT POST NEW MESSAGES =====
        // Student tries to post
        $studentPostAfterClose = $this->actingAs($studentUser)
            ->post(route('student.complaints.reply', $complaint), [
                'content' => 'Message after closure (should fail)',
            ]);
        $studentPostAfterClose->assertRedirect();
        $studentPostAfterClose->assertSessionHasErrors('content');

        // Admin tries to post
        $complaint->refresh();
        $adminPostAfterClose = $this->actingAs($admin)
            ->post(route('admin.complaints.reply', $complaint), [
                'content' => 'Admin message after closure (should fail)',
            ]);
        $adminPostAfterClose->assertRedirect();
        $adminPostAfterClose->assertSessionHasErrors('content');

        // Recipient tries to post
        $complaint->refresh();
        $recipientPostAfterClose = $this->actingAs($recipientUser)
            ->post(route('recipient.complaints.reply', $complaint), [
                'content' => 'Recipient message after closure (should fail)',
            ]);
        $recipientPostAfterClose->assertRedirect();
        $recipientPostAfterClose->assertSessionHasErrors('content');

        // Verify only the original message exists
        $this->assertDatabaseCount('thread_messages', 1);

        // ===== VERIFY ALL THREE CAN STILL VIEW MESSAGES (READ-ONLY) =====
        $complaint->refresh();

        // Student can see the message
        $this->actingAs($studentUser)
            ->get(route('student.complaints.show', $complaint))
            ->assertOk()
            ->assertSee('Message before closure');

        // Recipient can see the message
        $complaint->refresh();
        $this->actingAs($recipientUser)
            ->get(route('recipient.complaints.show', $complaint))
            ->assertOk()
            ->assertSee('Message before closure');

        // Admin can see the message
        $complaint->refresh();
        $this->actingAs($admin)
            ->get(route('admin.complaints.show', $complaint))
            ->assertOk()
            ->assertSee('Message before closure');
    }
}
