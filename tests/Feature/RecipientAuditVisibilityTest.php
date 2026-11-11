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

class RecipientAuditVisibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_referral_note_is_hidden_from_recipient_notification_and_ticket_page(): void
    {
        [, $recipient, $complaint] = $this->createAssignedTicket(Ticket::STATUS_REFERRED, [
            'referred_to' => 'Campus Disciplinary Committee',
        ]);
        $referralNote = 'REFERRAL-NOTE-PRIVATE-7391';
        AuditLog::log(
            $complaint->ticket->id,
            'ticket_referred',
            $this->admin()->id,
            "Ticket referred to Campus Disciplinary Committee. {$referralNote} The College Dean assessed this matter and endorsed it to the Campus Disciplinary Committee."
        );

        $this->actingAs($recipient)
            ->get(route('recipient.notifications'))
            ->assertOk()
            ->assertSee('Ticket sent outside Bulan Campus to Campus Disciplinary Committee; SDS is awaiting the reported outcome.')
            ->assertDontSee($referralNote)
            ->assertDontSee('The College Dean assessed this matter');

        $this->actingAs($recipient)
            ->get(route('recipient.tickets.show', $complaint))
            ->assertOk()
            ->assertSee('Campus Disciplinary Committee')
            ->assertDontSee($referralNote)
            ->assertDontSee('The College Dean assessed this matter');
    }

    public function test_escalation_reason_remains_visible_to_the_escalation_target(): void
    {
        [, $recipient, $complaint] = $this->createAssignedTicket(Ticket::STATUS_ESCALATED);
        $reason = 'ESCALATION-REASON-TARGET-4826';
        AuditLog::log(
            $complaint->ticket->id,
            'ticket_escalated',
            $this->admin()->id,
            "Ticket escalated to recipient. Reason: {$reason}"
        );

        $this->actingAs($recipient)
            ->get(route('recipient.notifications'))
            ->assertOk()
            ->assertSee($reason);
    }

    public function test_student_timeline_omits_own_messages_and_keeps_other_events(): void
    {
        [$student, $recipient, $complaint] = $this->createAssignedTicket(Ticket::STATUS_ASSIGNED);
        AuditLog::log($complaint->ticket->id, 'ticket_assigned', $this->admin()->id, 'Ticket assigned.');
        AuditLog::log($complaint->ticket->id, 'message_posted', $student->id, 'Student posted a reply message.');
        AuditLog::log($complaint->ticket->id, 'message_posted', $recipient->id, 'Recipient posted a reply message.');

        $response = $this->actingAs($student)
            ->get(route('student.complaints.show', $complaint))
            ->assertOk()
            ->assertSee('Ticket assigned')
            ->assertSee('Ticket Details');

        $this->assertSame(
            1,
            substr_count($response->getContent(), '<p class="text-sm font-medium text-foreground">Message posted</p>')
        );
    }

    private function admin(): User
    {
        return User::factory()->create([
            'role' => User::ROLE_SDS_ADMIN,
            'is_active' => true,
            'email_verified_at' => now(),
            'must_change_password' => false,
        ]);
    }

    /**
     * @return array{0: User, 1: User, 2: Complaint}
     */
    private function createAssignedTicket(string $status, array $attributes = []): array
    {
        $student = User::factory()->create([
            'role' => User::ROLE_STUDENT,
            'is_active' => true,
            'email_verified_at' => now(),
            'must_change_password' => false,
        ]);
        $studentProfile = Student::create([
            'user_id' => $student->id,
            'student_id' => 'S'.fake()->unique()->numerify('######'),
            'college' => 'CICT',
            'program' => 'BSIT',
            'year_level' => '2nd Year',
            'block' => 'A',
        ]);
        $recipient = User::factory()->create([
            'role' => User::ROLE_RECIPIENT,
            'is_active' => true,
            'email_verified_at' => now(),
            'must_change_password' => false,
        ]);
        Recipient::create([
            'user_id' => $recipient->id,
            'staff_id' => 'R'.fake()->unique()->numerify('######'),
            'unit' => 'Student Affairs',
            'designation' => 'Officer',
        ]);
        $category = ComplaintCategory::create([
            'name' => 'Student Concern',
            'is_active' => true,
        ]);
        $complaint = Complaint::create([
            'reference_number' => Complaint::generateReferenceNumber(),
            'student_id' => $studentProfile->id,
            'category_id' => $category->id,
            'subject_title' => 'Ticket visibility test',
            'description' => 'Ticket visibility test description.',
            'is_anonymous' => false,
            'status' => $status,
        ]);
        Ticket::create($attributes + [
            'complaint_id' => $complaint->id,
            'status' => $status,
            'classification' => Ticket::CLASSIFICATION_NEEDS_RESOLUTION,
            'jurisdiction' => Ticket::JURISDICTION_RECIPIENT,
            'assigned_to' => $recipient->id,
            'current_handler_id' => $recipient->id,
        ]);

        return [$student, $recipient, $complaint->fresh(['ticket'])];
    }
}
