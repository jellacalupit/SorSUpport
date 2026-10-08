<?php

namespace Tests\Feature;

use App\Models\Complaint;
use App\Models\ComplaintCategory;
use App\Models\Recipient;
use App\Models\Student;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TicketListPagingTest extends TestCase
{
    use RefreshDatabase;

    protected User $student;

    protected User $recipient;

    protected function setUp(): void
    {
        parent::setUp();

        $this->student = User::factory()->create(['role' => User::ROLE_STUDENT, 'email_verified_at' => now(), 'must_change_password' => false]);
        Student::create(['user_id' => $this->student->id, 'student_id' => 'S9001', 'college' => 'CICT', 'program' => 'BSIT', 'year_level' => '4', 'block' => '5']);

        $this->recipient = User::factory()->create(['role' => User::ROLE_RECIPIENT, 'email_verified_at' => now(), 'must_change_password' => false]);
        Recipient::create(['user_id' => $this->recipient->id, 'staff_id' => '210612', 'unit' => 'CICT', 'designation' => 'BSIT PC']);

        $category = ComplaintCategory::create(['name' => 'Grades and Examinations', 'is_active' => true]);

        foreach (range(1, 17) as $number) {
            $complaint = Complaint::create([
                'reference_number' => Complaint::generateReferenceNumber(),
                'student_id' => $this->student->student->id,
                'category_id' => $category->id,
                'subject_title' => sprintf('Ticket number %02d', $number),
                'description' => 'Paging coverage.',
                'is_anonymous' => false,
                'status' => Complaint::STATUS_SUBMITTED,
            ]);
            Ticket::create(['complaint_id' => $complaint->id, 'status' => Ticket::STATUS_ASSIGNED, 'assigned_to' => $this->recipient->id]);
        }
    }

    public function test_the_student_list_shows_fifteen_and_loads_the_rest_on_phones(): void
    {
        $page = $this->actingAs($this->student)->get(route('student.complaints.index'))->assertOk()->getContent();

        $this->assertSame(15, substr_count($page, 'Ticket number '));
        $this->assertStringContainsString('data-ticket-more data-next-url="' . e(route('student.complaints.index', ['page' => 2])) . '"', $page);
        $this->assertStringContainsString('mt-6 hidden md:block', $page);

        $last = $this->actingAs($this->student)->get(route('student.complaints.index', ['page' => 2]))->assertOk()->getContent();
        $this->assertSame(2, substr_count($last, 'Ticket number '));
        $this->assertStringNotContainsString('data-ticket-more', $last);
    }

    public function test_the_staff_list_shows_fifteen_and_loads_the_rest_on_phones(): void
    {
        $page = $this->actingAs($this->recipient)->get(route('recipient.tickets.index'))->assertOk()->getContent();

        $this->assertSame(15, substr_count($page, 'Ticket number '));
        $this->assertStringContainsString('data-ticket-more', $page);
    }

    public function test_the_student_home_page_shows_the_ten_most_recent_tickets(): void
    {
        $page = $this->actingAs($this->student)->get(route('student.dashboard'))->assertOk()->getContent();

        $this->assertSame(10, substr_count($page, 'Ticket number '));
    }
}
