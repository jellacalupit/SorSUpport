<?php

namespace Tests\Feature;

use App\Models\Complaint;
use App\Models\ComplaintCategory;
use App\Models\Recipient;
use App\Models\Student;
use App\Models\Ticket;
use App\Models\User;
use App\Services\TicketWorkflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TicketDisplayStatusTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected User $student;

    protected User $recipientUser;

    protected Recipient $recipient;

    protected ComplaintCategory $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role' => User::ROLE_SDS_ADMIN]);
        $this->student = User::factory()->create(['role' => User::ROLE_STUDENT]);
        Student::create([
            'user_id' => $this->student->id,
            'student_id' => 'DISPLAY-STATUS-1',
            'college' => 'CICT',
            'program' => 'BSIT',
            'year_level' => '1',
            'block' => 'A',
        ]);
        $this->recipientUser = User::factory()->create(['role' => User::ROLE_RECIPIENT]);
        $this->recipient = Recipient::create([
            'user_id' => $this->recipientUser->id,
            'staff_id' => 'DISPLAY-STATUS-STAFF',
            'unit' => 'Registrar Office',
            'designation' => 'Officer',
        ]);
        $this->category = ComplaintCategory::create(['name' => 'Student Services', 'is_active' => true]);
    }

    public function test_display_status_for_every_classification_and_stored_status(): void
    {
        foreach (array_keys(Ticket::DISPLAY_STATUS_LABELS) as $status) {
            foreach ([
                Ticket::CLASSIFICATION_NEEDS_RESOLUTION,
                Ticket::CLASSIFICATION_INFORMATIONAL,
                Ticket::CLASSIFICATION_INVALID,
                null,
            ] as $classification) {
                $ticket = new Ticket(['status' => $status, 'classification' => $classification]);
                $expected = match (true) {
                    $classification === Ticket::CLASSIFICATION_NEEDS_RESOLUTION => Ticket::DISPLAY_STATUS_LABELS[$status],
                    $classification === null && in_array($status, Ticket::UNCLASSIFIED_DISPLAY_STATUSES, true) => Ticket::DISPLAY_STATUS_LABELS[$status],
                    default => null,
                };

                $this->assertSame($expected, $ticket->displayStatus(), "{$classification} / {$status}");
            }
        }

        $this->assertNull((new Ticket(['status' => 'unknown', 'classification' => Ticket::CLASSIFICATION_NEEDS_RESOLUTION]))->displayStatus());
    }

    public function test_outcome_labels_cover_informational_forwarded_and_invalid_tickets(): void
    {
        $forwarded = new Ticket([
            'status' => Ticket::STATUS_SUBMITTED,
            'classification' => Ticket::CLASSIFICATION_INFORMATIONAL,
            'forwarded_to' => $this->recipient->id,
            'forwarded_at' => now(),
        ]);
        $informational = new Ticket([
            'status' => Ticket::STATUS_CLOSED,
            'classification' => Ticket::CLASSIFICATION_INFORMATIONAL,
        ]);
        $invalid = new Ticket([
            'status' => Ticket::STATUS_CLOSED,
            'classification' => Ticket::CLASSIFICATION_INVALID,
        ]);

        $this->assertSame('Forwarded for information', $forwarded->outcomeLabel());
        $this->assertSame('Recorded for information', $informational->outcomeLabel());
        $this->assertSame('Not accepted', $invalid->outcomeLabel());
        $this->assertNull((new Ticket(['status' => Ticket::STATUS_SUBMITTED]))->outcomeLabel());
    }

    public function test_informational_and_invalid_display_does_not_change_workflow_action_eligibility(): void
    {
        $workflow = app(TicketWorkflow::class);
        $actors = [$this->admin, $this->student, $this->recipientUser];

        foreach (array_keys(Ticket::DISPLAY_STATUS_LABELS) as $status) {
            $baseline = $this->ticket($status, Ticket::CLASSIFICATION_NEEDS_RESOLUTION);
            $baselineActions = array_map(
                fn (User $actor) => $workflow->availableActions($actor, $baseline),
                $actors
            );

            foreach ([Ticket::CLASSIFICATION_INFORMATIONAL, Ticket::CLASSIFICATION_INVALID] as $classification) {
                $ticket = $this->ticket($status, $classification);
                $storedStatus = $ticket->status;
                $actions = array_map(
                    fn (User $actor) => $workflow->availableActions($actor, $ticket),
                    $actors
                );

                $ticket->displayStatus();
                $ticket->outcomeLabel();

                $this->assertSame($baselineActions, $actions, "{$classification} / {$status}");
                $this->assertSame($storedStatus, $ticket->fresh()->status);
            }
        }
    }

    public function test_sds_all_tickets_status_filter_is_disabled_or_class_scoped(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.complaints.index'))
            ->assertOk()
            ->assertSee('data-status-filter-disabled="true"', false)
            ->assertSee('Select Needs Resolution to filter by status');

        $needsResolution = $this->actingAs($this->admin)
            ->get(route('admin.complaints.index', ['classification_filter' => Ticket::CLASSIFICATION_NEEDS_RESOLUTION]))
            ->assertOk();

        foreach (array_keys(Ticket::DISPLAY_STATUS_LABELS) as $status) {
            $needsResolution->assertSee('data-status-option="' . $status . '"', false);
        }

        $unclassified = $this->actingAs($this->admin)
            ->get(route('admin.complaints.index', ['classification_filter' => 'unclassified']))
            ->assertOk();
        $this->assertMatchesRegularExpression(
            '/<details data-status-filter-enabled.*?<\/details>/s',
            $unclassified->getContent()
        );
        preg_match('/<details data-status-filter-enabled.*?<\/details>/s', $unclassified->getContent(), $statusDropdown);
        preg_match_all('/data-status-option="([^"]*)"/', $statusDropdown[0], $statusValues);
        $this->assertSame(['', Ticket::STATUS_SUBMITTED, Ticket::STATUS_NEEDS_CLARIFICATION], $statusValues[1]);
        $unclassified->assertSee('Unclassified');
    }

    public function test_analytics_status_filter_is_disabled_and_limits_unclassified_to_two_statuses(): void
    {
        $disabled = $this->actingAs($this->admin)
            ->get(route('admin.analytics.index'))
            ->assertOk()
            ->assertSee('data-disabled="true"', false)
            ->assertSee('Select Needs Resolution to filter by status')
            ->assertSee('data-value="unclassified"', false);

        $needsResolution = $this->actingAs($this->admin)
            ->get(route('admin.analytics.index', ['classification' => Ticket::CLASSIFICATION_NEEDS_RESOLUTION]))
            ->assertOk()
            ->assertSee('data-disabled="false"', false);
        foreach (array_keys(Ticket::DISPLAY_STATUS_LABELS) as $status) {
            $this->assertDoesNotMatchRegularExpression(
                '/<button[^>]*data-value="' . preg_quote($status, '/') . '"[^>]*hidden/',
                $this->statusDropdown($needsResolution->getContent())
            );
        }

        $unclassified = $this->actingAs($this->admin)
            ->get(route('admin.analytics.index', ['classification' => 'unclassified']))
            ->assertOk()
            ->assertSee('In review');
        $statusDropdown = $this->statusDropdown($unclassified->getContent());
        foreach (Ticket::DISPLAY_STATUS_LABELS as $status => $label) {
            $pattern = '/<button[^>]*data-value="' . preg_quote($status, '/') . '"[^>]*hidden/';
            if (in_array($status, Ticket::UNCLASSIFIED_DISPLAY_STATUSES, true)) {
                $this->assertDoesNotMatchRegularExpression($pattern, $statusDropdown);
            } else {
                $this->assertMatchesRegularExpression($pattern, $statusDropdown);
            }
        }
        $this->assertStringContainsString("statusInput.value = '';", $unclassified->getContent());
    }

    public function test_sds_all_tickets_uses_a_dash_for_informational_and_invalid_statuses(): void
    {
        $this->ticket(Ticket::STATUS_SUBMITTED, Ticket::CLASSIFICATION_INFORMATIONAL);
        $this->ticket(Ticket::STATUS_CLOSED, Ticket::CLASSIFICATION_INVALID);

        $response = $this->actingAs($this->admin)
            ->get(route('admin.complaints.index'))
            ->assertOk()
            ->assertDontSee('Recorded for information')
            ->assertDontSee('Not accepted');

        $this->assertSame(2, substr_count($response->getContent(), 'data-ticket-status-empty>-'));
    }

    public function test_in_review_filter_returns_the_stored_needs_clarification_status(): void
    {
        $needsClarification = $this->ticket(Ticket::STATUS_NEEDS_CLARIFICATION, Ticket::CLASSIFICATION_NEEDS_RESOLUTION);
        $submitted = $this->ticket(Ticket::STATUS_SUBMITTED, Ticket::CLASSIFICATION_NEEDS_RESOLUTION);

        $response = $this->actingAs($this->admin)->get(route('admin.complaints.index', [
            'classification_filter' => Ticket::CLASSIFICATION_NEEDS_RESOLUTION,
            'status_filter' => Ticket::STATUS_NEEDS_CLARIFICATION,
        ]))->assertOk()
            ->assertSee($needsClarification->complaint->reference_number)
            ->assertDontSee($submitted->complaint->reference_number)
            ->assertSee('In review');

        $this->assertSame(Ticket::STATUS_NEEDS_CLARIFICATION, $needsClarification->fresh()->status);
        $this->assertSame(1, app(\App\Services\AnalyticsService::class)->getTotalTickets([
            'classification' => Ticket::CLASSIFICATION_NEEDS_RESOLUTION,
            'status' => Ticket::STATUS_NEEDS_CLARIFICATION,
        ]));
    }

    private function ticket(string $status, ?string $classification): Ticket
    {
        $complaint = Complaint::create([
            'reference_number' => Complaint::generateReferenceNumber(),
            'student_id' => $this->student->student->id,
            'category_id' => $this->category->id,
            'subject_title' => 'Display status fixture',
            'description' => 'Display status fixture description.',
            'personnel_involved' => 'Not applicable',
            'is_anonymous' => false,
            'status' => $status,
        ]);

        $underReview = in_array($status, [Ticket::STATUS_SUBMITTED, Ticket::STATUS_NEEDS_CLARIFICATION], true);

        return Ticket::create([
            'complaint_id' => $complaint->id,
            'status' => $status,
            'classification' => $classification,
            'assigned_to' => $underReview ? null : $this->recipientUser->id,
            'current_handler_id' => $underReview ? $this->admin->id : $this->recipientUser->id,
            'resolved_at' => in_array($status, [Ticket::STATUS_RESOLVED, Ticket::STATUS_CLOSED], true) ? now() : null,
            'closed_at' => $status === Ticket::STATUS_CLOSED ? now() : null,
            'forwarded_to' => $classification === Ticket::CLASSIFICATION_INFORMATIONAL ? $this->recipient->id : null,
            'forwarded_at' => $classification === Ticket::CLASSIFICATION_INFORMATIONAL ? now() : null,
        ]);
    }

    private function statusDropdown(string $html): string
    {
        preg_match('/<details[^>]*data-analytics-status-dropdown.*?<\/details>/s', $html, $matches);

        return $matches[0] ?? '';
    }

}
