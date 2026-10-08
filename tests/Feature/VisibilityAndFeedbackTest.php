<?php

namespace Tests\Feature;

use App\Exports\AnalyticsReportExport;
use App\Models\AuditLog;
use App\Models\Complaint;
use App\Models\ComplaintCategory;
use App\Models\Recipient;
use App\Models\Student;
use App\Models\Ticket;
use App\Models\User;
use App\Services\AnalyticsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VisibilityAndFeedbackTest extends TestCase
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

        $this->admin = $this->user(User::ROLE_SDS_ADMIN, 'Ada', 'Admin');
        $this->student = $this->user(User::ROLE_STUDENT, 'Sam', 'Student');
        $this->otherStudent = $this->user(User::ROLE_STUDENT, 'Olive', 'Other');
        $this->handler = $this->user(User::ROLE_RECIPIENT, 'Rey', 'Registrar');
        $this->otherRecipient = $this->user(User::ROLE_RECIPIENT, 'Dana', 'Dean');

        Student::create(['user_id' => $this->student->id, 'student_id' => '20240001', 'college' => 'CICT', 'program' => 'BSIT', 'year_level' => '1', 'block' => '1']);
        Student::create(['user_id' => $this->otherStudent->id, 'student_id' => '20240002', 'college' => 'CBME', 'program' => 'BSBA', 'year_level' => '2', 'block' => '1']);
        Recipient::create(['user_id' => $this->handler->id, 'staff_id' => 'STAFF-1', 'unit' => 'Registrar', 'designation' => 'Registrar']);
        Recipient::create(['user_id' => $this->otherRecipient->id, 'staff_id' => 'STAFF-2', 'unit' => 'CICT', 'designation' => 'Dean']);

        $this->category = ComplaintCategory::create(['name' => 'Student Services', 'is_active' => true]);
        $this->category->suggestedRecipients()->attach([$this->handler->recipient->id, $this->otherRecipient->recipient->id]);
    }

    protected function user(string $role, string $first, string $last): User
    {
        return User::factory()->create([
            'role' => $role,
            'name' => "{$first} {$last}",
            'first_name' => $first,
            'last_name' => $last,
            'is_active' => true,
            'email_verified_at' => now(),
            'must_change_password' => false,
        ]);
    }

    protected function ticket(string $status, array $attributes = [], ?User $student = null, bool $anonymous = false): Ticket
    {
        $complaint = Complaint::create([
            'reference_number' => Complaint::generateReferenceNumber(),
            'student_id' => ($student ?? $this->student)->student->id,
            'category_id' => $this->category->id,
            'subject_title' => 'Transcript request',
            'description' => 'My transcript has not been released.',
            'is_anonymous' => $anonymous,
            'status' => $status,
        ]);

        $underReview = in_array($status, [Ticket::STATUS_SUBMITTED, Ticket::STATUS_NEEDS_CLARIFICATION], true);

        return Ticket::create($attributes + [
            'complaint_id' => $complaint->id,
            'status' => $status,
            'classification' => $underReview ? null : Ticket::CLASSIFICATION_NEEDS_RESOLUTION,
            'assigned_to' => $underReview ? null : $this->handler->id,
            'current_handler_id' => $underReview ? $this->admin->id : $this->handler->id,
        ]);
    }

    protected function closedTicket(string $closureType = Ticket::CLOSURE_RESOLVED_ACCEPTED, array $attributes = [], ?User $student = null, bool $anonymous = false): Ticket
    {
        return $this->ticket(Ticket::STATUS_CLOSED, $attributes + [
            'closure_type' => $closureType,
            'closed_at' => now(),
            'resolved_at' => now(),
        ], $student, $anonymous);
    }

    // ------------------------------------------------------------------ forwarded informational tickets

    public function test_a_forwarded_informational_ticket_is_visible_read_only_to_the_recipient(): void
    {
        $ticket = $this->ticket(Ticket::STATUS_SUBMITTED);
        $complaint = $ticket->complaint;

        $this->actingAs($this->admin)
            ->post(route('admin.tickets.forward-informational-close', $ticket), ['recipient_id' => $this->handler->recipient->id])
            ->assertSessionHasNoErrors();

        $ticket->refresh();
        $this->assertSame(Ticket::STATUS_CLOSED, $ticket->status);
        $this->assertSame($this->handler->recipient->id, (int) $ticket->forwarded_to);
        $this->assertNull($ticket->assigned_to);

        // The recipient it was forwarded to finds it in their list and can open it.
        $this->actingAs($this->handler)->get(route('recipient.tickets.index'))
            ->assertOk()
            ->assertSee($complaint->reference_number);

        $this->actingAs($this->handler)->get(route('recipient.complaints.show', $complaint))
            ->assertOk()
            ->assertSee('Forwarded for your information')
            ->assertSee('My transcript has not been released.')
            ->assertDontSee('Mark as Resolved')
            ->assertDontSee('Acknowledge Ticket');

        // It is a record only: nothing can be done on it.
        $this->actingAs($this->handler)
            ->post(route('recipient.complaints.reply', $complaint), ['content' => 'Noted.'])
            ->assertForbidden();
        $this->actingAs($this->handler)
            ->post(route('recipient.complaints.acknowledge', $complaint))
            ->assertForbidden();
        $this->actingAs($this->handler)
            ->patch(route('recipient.complaints.update-status', $complaint), ['status' => 'resolved', 'resolution_type' => Ticket::RESOLUTION_ACTION_TAKEN, 'resolution_message' => 'Done.'])
            ->assertForbidden();

        $this->assertSame(Ticket::STATUS_CLOSED, $ticket->fresh()->status);

        // Nobody else in another office can see it.
        $this->actingAs($this->otherRecipient)->get(route('recipient.complaints.show', $complaint))->assertForbidden();
        $this->actingAs($this->otherRecipient)->get(route('recipient.tickets.index'))->assertOk()->assertDontSee($complaint->reference_number);
    }

    public function test_an_assigned_ticket_shows_no_forwarded_notice(): void
    {
        $ticket = $this->ticket(Ticket::STATUS_IN_PROGRESS);

        $this->actingAs($this->handler)->get(route('recipient.complaints.show', $ticket->complaint))
            ->assertOk()
            ->assertDontSee('Forwarded for your information')
            ->assertSee('Mark as Resolved');
    }

    // ------------------------------------------------------------------ days open and last action

    public function test_days_open_and_days_since_the_last_action_are_counted(): void
    {
        $this->travelTo(now()->subDays(10));
        $ticket = $this->ticket(Ticket::STATUS_IN_PROGRESS);
        AuditLog::log($ticket->id, 'ticket_assigned', $this->admin->id, 'Assigned.');

        $this->travelTo(now()->addDays(6));
        AuditLog::log($ticket->id, 'message_posted', $this->handler->id, 'Recipient posted a reply message.');

        // Emails the system sends are not an action by anyone.
        $this->travelTo(now()->addDays(2));
        AuditLog::create(['ticket_id' => $ticket->id, 'performed_by' => null, 'action' => 'email_notification_sent', 'details' => 'Email sent.']);

        $this->travelBack();
        $ticket = $ticket->fresh();

        $this->assertSame(10, $ticket->daysOpen());
        $this->assertSame(4, $ticket->daysSinceLastAction());

        $this->actingAs($this->admin)->get(route('admin.complaints.show', $ticket->complaint))
            ->assertOk()
            ->assertSee('Days Open')
            ->assertSee('10 days')
            ->assertSee('4 days ago');

        // Lists point out how long a ticket has been open and idle.
        $this->actingAs($this->handler)->get(route('recipient.tickets.index'))
            ->assertOk()
            ->assertSee('Open 10d · no action for 4d');

        // Students see the days on the ticket, but not the idle flag in their list.
        $this->actingAs($this->student)->get(route('student.complaints.index'))->assertOk()->assertDontSee('no action for');
    }

    public function test_a_closed_ticket_shows_the_days_it_took_and_no_idle_time(): void
    {
        $this->travelTo(now()->subDays(8));
        $ticket = $this->ticket(Ticket::STATUS_IN_PROGRESS);
        $this->travelBack();

        $ticket->update(['status' => Ticket::STATUS_CLOSED, 'closure_type' => Ticket::CLOSURE_WITHDRAWN, 'closed_at' => now()->subDays(3)]);
        $ticket = $ticket->fresh();

        $this->assertSame(5, $ticket->daysOpen());
        $this->assertNull($ticket->daysSinceLastAction());

        $this->actingAs($this->student)->get(route('student.complaints.show', $ticket->complaint))
            ->assertOk()
            ->assertSee('Days To Close')
            ->assertSee('5 days')
            ->assertDontSee('Last Action');
    }

    public function test_a_ticket_submitted_today_has_been_open_zero_days(): void
    {
        $ticket = $this->ticket(Ticket::STATUS_SUBMITTED);

        $this->assertSame(0, $ticket->daysOpen());
        $this->assertSame(0, $ticket->daysSinceLastAction());

        $this->actingAs($this->admin)->get(route('admin.complaints.show', $ticket->complaint))->assertOk()->assertSee('0 days')->assertSee('Today');
    }

    // ------------------------------------------------------------------ satisfaction rating

    public function test_the_student_rates_a_resolved_and_closed_ticket_once(): void
    {
        $ticket = $this->closedTicket();
        $complaint = $ticket->complaint;
        $url = route('student.complaints.rate', $complaint);

        $this->actingAs($this->student)->get(route('student.complaints.show', $complaint))
            ->assertOk()
            ->assertSee('How satisfied are you with how this was handled?');

        $this->actingAs($this->student)->post($url, [])->assertSessionHasErrors('satisfaction_rating');
        $this->actingAs($this->student)->post($url, ['satisfaction_rating' => 6])->assertSessionHasErrors('satisfaction_rating');
        $this->actingAs($this->student)->post($url, ['satisfaction_rating' => 0])->assertSessionHasErrors('satisfaction_rating');
        $this->actingAs($this->otherStudent)->post($url, ['satisfaction_rating' => 1])->assertForbidden();
        $this->actingAs($this->handler)->post($url, ['satisfaction_rating' => 5])->assertForbidden();

        $this->assertNull($ticket->fresh()->satisfaction_rating);

        $this->actingAs($this->student)
            ->post($url, ['satisfaction_rating' => 4, 'satisfaction_comment' => 'Quick and courteous.'])
            ->assertSessionHasNoErrors();

        $ticket->refresh();
        $this->assertSame(4, $ticket->satisfaction_rating);
        $this->assertSame('Quick and courteous.', $ticket->satisfaction_comment);
        $this->assertNotNull($ticket->rated_at);
        $this->assertDatabaseHas('audit_logs', ['ticket_id' => $ticket->id, 'action' => 'satisfaction_rated']);

        // It cannot be changed afterwards.
        $this->actingAs($this->student)->post($url, ['satisfaction_rating' => 1])->assertForbidden();
        $this->assertSame(4, $ticket->fresh()->satisfaction_rating);

        $this->actingAs($this->student)->get(route('student.complaints.show', $complaint))
            ->assertOk()
            ->assertSee('4 out of 5')
            ->assertDontSee('How satisfied are you with how this was handled?');

        // The office that handled it and the admin see the feedback.
        $this->actingAs($this->handler)->get(route('recipient.complaints.show', $complaint))->assertOk()->assertSee('4 out of 5')->assertSee('Quick and courteous.');
        $this->actingAs($this->admin)->get(route('admin.complaints.show', $complaint))->assertOk()->assertSee('4 out of 5');
    }

    public function test_only_resolved_tickets_can_be_rated(): void
    {
        $unrated = [
            $this->closedTicket(Ticket::CLOSURE_WITHDRAWN),
            $this->closedTicket(Ticket::CLOSURE_INVALID),
            $this->closedTicket(Ticket::CLOSURE_INFORMATIONAL),
            $this->ticket(Ticket::STATUS_RESOLVED),
            $this->ticket(Ticket::STATUS_IN_PROGRESS),
        ];

        foreach ($unrated as $ticket) {
            $this->assertFalse($ticket->canBeRated());

            $this->actingAs($this->student)
                ->post(route('student.complaints.rate', $ticket->complaint), ['satisfaction_rating' => 5])
                ->assertForbidden();

            $this->actingAs($this->student)->get(route('student.complaints.show', $ticket->complaint))
                ->assertOk()
                ->assertDontSee('How satisfied are you with how this was handled?');
        }

        $this->assertTrue($this->closedTicket(Ticket::CLOSURE_RESOLVED)->canBeRated());
    }

    // ------------------------------------------------------------------ analytics

    public function test_analytics_break_tickets_down_by_college_program_resolution_escalation_and_rating(): void
    {
        $this->closedTicket(Ticket::CLOSURE_RESOLVED_ACCEPTED, ['resolution_type' => Ticket::RESOLUTION_ACTION_TAKEN, 'satisfaction_rating' => 5]);
        $this->closedTicket(Ticket::CLOSURE_RESOLVED, ['resolution_type' => Ticket::RESOLUTION_ACTION_TAKEN, 'satisfaction_rating' => 2], $this->otherStudent);
        $this->closedTicket(Ticket::CLOSURE_WITHDRAWN);
        $this->ticket(Ticket::STATUS_RESOLVED, ['resolution_type' => Ticket::RESOLUTION_EXPLANATION], $this->otherStudent);
        $hidden = $this->ticket(Ticket::STATUS_IN_PROGRESS, [], $this->student, anonymous: true);
        $escalated = $this->ticket(Ticket::STATUS_ESCALATED);

        AuditLog::log($escalated->id, 'ticket_escalated', $this->admin->id, 'Escalated.');
        AuditLog::log($escalated->id, 'ticket_escalated', $this->admin->id, 'Escalated again.');
        AuditLog::log($hidden->id, 'ticket_escalated', $this->admin->id, 'Escalated.');

        $breakdowns = app(AnalyticsService::class)->getBreakdowns();

        // The hidden-identity ticket is not attributed to the student's college or program.
        $this->assertSame(['CICT' => 3, 'CBME' => 2, 'Not disclosed' => 1], $breakdowns['colleges']);
        $this->assertSame(['BSIT' => 3, 'BSBA' => 2, 'Not disclosed' => 1], $breakdowns['programs']);

        $this->assertSame(['Corrective action taken' => 2, 'Explanation or information provided' => 1], $breakdowns['resolution_types']);
        $this->assertSame(1, $breakdowns['closure_reasons']['Withdrawn by the student']);
        $this->assertSame(1, $breakdowns['closure_reasons']['Resolution accepted by the student']);
        $this->assertArrayNotHasKey('Not a valid complaint', $breakdowns['closure_reasons']);

        $this->assertSame([
            'Not escalated' => 4,
            'Escalated once' => 1,
            'Escalated twice' => 1,
            'Escalated three or more times' => 0,
        ], $breakdowns['escalation_levels']);

        $this->assertSame(2, $breakdowns['satisfaction']['count']);
        $this->assertSame(3.5, $breakdowns['satisfaction']['average']);
        $this->assertSame([5 => 1, 4 => 0, 3 => 0, 2 => 1, 1 => 0], $breakdowns['satisfaction']['distribution']);
    }

    public function test_the_analytics_page_and_exports_include_the_breakdowns(): void
    {
        $this->closedTicket(Ticket::CLOSURE_RESOLVED_ACCEPTED, ['resolution_type' => Ticket::RESOLUTION_SETTLED, 'satisfaction_rating' => 4]);

        $this->actingAs($this->admin)->get('/admin/analytics')
            ->assertOk()
            ->assertSee('Tickets by college')
            ->assertSee('Tickets by program')
            ->assertSee('How tickets were resolved')
            ->assertSee('Settled between the parties')
            ->assertSee('Why tickets were closed')
            ->assertSee('Escalation level')
            ->assertSee('Student satisfaction')
            ->assertSee('4.0');

        $reportData = app(AnalyticsService::class)->getReportData();
        $rows = collect((new AnalyticsReportExport($reportData))->array())->map(fn ($row) => implode(' | ', $row));

        foreach (['Tickets by College', 'CICT | 1', 'Tickets by Program', 'BSIT | 1', 'How Tickets Were Resolved', 'Settled between the parties | 1', 'Escalation Level', 'Student Satisfaction', 'Average rating | 4', '4 out of 5 | 1'] as $expected) {
            $this->assertTrue($rows->contains($expected), "The Excel export is missing: {$expected}");
        }

        $this->actingAs($this->admin)->get(route('admin.analytics.export.excel'))->assertOk();
        $this->actingAs($this->admin)->get(route('admin.analytics.export.pdf'))->assertOk();

        $this->actingAs($this->admin)->get(route('admin.analytics.export.pdf'));
        $this->assertStringContainsString('Tickets by College', view('admin.analytics.report', ['reportData' => $reportData, 'filters' => []])->render());
    }

    public function test_the_dashboard_shows_the_same_recipient_performance_as_analytics(): void
    {
        // Three tickets with the same handler: one resolved and already closed, one resolved,
        // one still in progress. Analytics counts two of three as resolved.
        $this->ticket(Ticket::STATUS_CLOSED, ['resolved_at' => now(), 'closed_at' => now()]);
        $this->ticket(Ticket::STATUS_RESOLVED, ['resolved_at' => now()]);
        $this->ticket(Ticket::STATUS_IN_PROGRESS);

        $row = collect(app(AnalyticsService::class)->getDashboardData()['recipients'])->firstWhere('user_id', $this->handler->id);
        $this->assertSame(3, $row['assigned']);
        $this->assertSame(2, $row['resolved']);

        $top = $this->actingAs($this->admin)->get(route('admin.dashboard'))->assertOk()->viewData('topPerformingRecipients');
        $card = $top->firstWhere('staff_id', 'STAFF-1');

        $this->assertNotNull($card);
        $this->assertSame(67, $card->performance_percentage);
        $this->assertSame(2, $card->resolved_count);
        // People who hold no tickets are not listed with an empty bar.
        $this->assertNull($top->firstWhere('staff_id', $this->otherRecipient->recipient->staff_id));
    }

    /**
     * Record a step on a ticket as if it happened some days ago.
     */
    protected function step(Ticket $ticket, string $action, int $daysAgo, ?User $by = null): void
    {
        $log = \App\Models\AuditLog::log($ticket->id, $action, ($by ?? $this->admin)->id, 'Recorded for the test.');
        $log->forceFill(['created_at' => now()->subDays($daysAgo), 'updated_at' => now()->subDays($daysAgo)])->save();
    }

    public function test_analytics_measure_how_long_tickets_wait_at_each_stage(): void
    {
        // Filed 5 days ago, reviewed after 2 days, acknowledged a day later, still in progress.
        $ticket = $this->ticket(Ticket::STATUS_IN_PROGRESS);
        $ticket->complaint->forceFill(['created_at' => now()->subDays(5)])->save();
        $this->step($ticket, 'complaint_submitted', 5, $this->student);
        $this->step($ticket, 'ticket_assigned', 3);
        $this->step($ticket, 'ticket_acknowledged', 2, $this->handler);

        // And one nobody has reviewed for 4 days.
        $waiting = $this->ticket(Ticket::STATUS_SUBMITTED);
        $waiting->complaint->forceFill(['created_at' => now()->subDays(4)])->save();
        $this->step($waiting, 'complaint_submitted', 4, $this->student);

        $times = app(AnalyticsService::class)->getDashboardData()['waiting'];

        $this->assertEqualsWithDelta(2.0, $times['review_days'], 0.05);
        $this->assertEqualsWithDelta(1.0, $times['acknowledge_days'], 0.05);
        $this->assertSame(1, $times['awaiting_review']);
        $this->assertSame(4, $times['oldest_review_days']);
        $this->assertSame(1, $times['review_overdue']);
        $this->assertSame($waiting->complaint->reference_number, $times['longest']->first()['reference']);

        $this->actingAs($this->admin)->get('/admin/analytics')
            ->assertOk()
            ->assertSee('Waiting time')
            ->assertSee('Before the first review')
            ->assertSee('Waiting for review by the SDS Office');

        // The dashboard points the waiting ticket out; nothing is escalated for it.
        $this->actingAs($this->admin)->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Waiting 3 days or more:')
            ->assertSee('1 ticket for your review');
        $this->assertSame(Ticket::STATUS_SUBMITTED, $waiting->fresh()->status);

        $report = view('admin.analytics.report', ['reportData' => app(AnalyticsService::class)->getReportData(), 'filters' => [], 'sections' => ['waiting']])->render();
        $this->assertStringContainsString('Average wait before the first review', $report);
    }

    public function test_analytics_bars_are_as_long_as_their_percentage(): void
    {
        $other = ComplaintCategory::create(['name' => 'Library Services', 'is_active' => true]);
        foreach (range(1, 3) as $ignored) {
            $this->ticket(Ticket::STATUS_IN_PROGRESS);
        }
        $single = $this->ticket(Ticket::STATUS_IN_PROGRESS);
        $single->complaint->update(['category_id' => $other->id]);

        $page = $this->actingAs($this->admin)->get('/admin/analytics')->assertOk()->getContent();

        // Three of four tickets is 75% and one of four is 25%: the larger bar is not drawn full.
        $this->assertStringContainsString('bg-primary" style="width: 75%"', $page);
        $this->assertStringContainsString('bg-primary" style="width: 25%"', $page);
    }

    public function test_the_admin_chooses_which_parts_of_the_report_to_generate(): void
    {
        $reportData = app(AnalyticsService::class)->getReportData();

        // Only the chosen parts are printed, on the university letterhead.
        $report = view('admin.analytics.report', ['reportData' => $reportData, 'filters' => [], 'sections' => ['summary', 'colleges'], 'preparedBy' => 'Abbie Goyal'])->render();
        $this->assertStringContainsString('Sorsogon State University', $report);
        $this->assertStringContainsString('Tickets by College', $report);
        $this->assertStringContainsString('Prepared by:', $report);
        $this->assertStringNotContainsString('Tickets by Program', $report);
        $this->assertStringNotContainsString('Student Satisfaction', $report);

        $this->actingAs($this->admin)->get(route('admin.analytics.export.pdf', ['sections' => ['summary', 'statuses']]))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        $this->actingAs($this->admin)->from(route('admin.analytics.index'))->get(route('admin.analytics.export.pdf', ['sections' => ['not-a-part']]))
            ->assertRedirect(route('admin.analytics.index'))
            ->assertSessionHasErrors('sections');

        // The Generate Report window lists every part.
        $this->actingAs($this->admin)->get(route('admin.analytics.index'))
            ->assertOk()
            ->assertSee('name="sections[]" value="resolution_time"', false)
            ->assertSee('Download PDF');
    }

    public function test_resolution_time_in_the_report_is_in_days_and_never_negative(): void
    {
        $ticket = $this->ticket(Ticket::STATUS_RESOLVED);
        $ticket->complaint->forceFill(['created_at' => now()->subDays(3)])->save();
        $ticket->forceFill(['resolved_at' => now()])->save();

        $times = app(AnalyticsService::class)->getAverageResolutionTimePerCategory();

        $this->assertNotEmpty($times['data']);
        $this->assertEqualsWithDelta(3.0, $times['data'][0], 0.1);
    }

    public function test_analytics_show_empty_breakdowns_without_tickets(): void
    {
        $this->actingAs($this->admin)->get('/admin/analytics')
            ->assertOk()
            ->assertSee('No ratings in this period.')
            ->assertSee('No resolved tickets in this period.');
    }
}
