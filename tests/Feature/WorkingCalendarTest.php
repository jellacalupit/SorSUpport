<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\CalendarOverride;
use App\Models\Complaint;
use App\Models\ComplaintCategory;
use App\Models\EmailNotification;
use App\Models\Student;
use App\Models\SystemSetting;
use App\Models\Ticket;
use App\Models\User;
use App\Services\TicketReminderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class WorkingCalendarTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected User $student;

    protected User $recipient;

    protected ComplaintCategory $category;

    protected function setUp(): void
    {
        parent::setUp();

        config(['app.timezone' => 'Asia/Manila']);
        $this->admin = $this->user(User::ROLE_SDS_ADMIN);
        $this->student = $this->user(User::ROLE_STUDENT);
        Student::create([
            'user_id' => $this->student->id,
            'student_id' => 'CALENDAR-1',
            'college' => 'CICT',
            'program' => 'BSIT',
            'year_level' => '1',
            'block' => 'A',
        ]);
        $this->recipient = $this->user(User::ROLE_RECIPIENT);
        $this->category = ComplaintCategory::create(['name' => 'General', 'is_active' => true]);
    }

    public function test_a_holiday_delays_the_reminder_threshold_by_one_working_day(): void
    {
        SystemSetting::put('reminders.unreviewed_days', 3);
        $this->travelTo(Carbon::parse('2026-10-09 10:00', 'Asia/Manila'));
        $ticket = $this->ticket();
        CalendarOverride::create([
            'date' => '2026-10-12',
            'type' => CalendarOverride::TYPE_NON_WORKING,
            'description' => 'Proclaimed holiday',
            'created_by' => $this->admin->id,
        ]);
        $this->assertFalse(TicketReminderService::isWorkingDate(Carbon::parse('2026-10-12 08:00', 'Asia/Manila')));

        $this->travelTo(Carbon::parse('2026-10-14 10:00', 'Asia/Manila'));
        $service = app(TicketReminderService::class);
        $this->assertSame(0, $service->queueReminders());
        $this->assertDatabaseMissing('email_notifications', [
            'ticket_id' => $ticket->id,
            'type' => EmailNotification::TYPE_WAITING_REMINDER,
        ]);

        $this->travelTo(Carbon::parse('2026-10-15 10:00', 'Asia/Manila'));
        $this->assertSame(1, $service->queueReminders());
        $this->assertDatabaseHas('email_notifications', [
            'ticket_id' => $ticket->id,
            'type' => EmailNotification::TYPE_WAITING_REMINDER,
        ]);
    }

    public function test_working_saturday_override_counts_and_removal_restores_the_weekend_rule(): void
    {
        $saturday = Carbon::parse('2026-10-09 16:00', 'UTC');
        $sunday = Carbon::parse('2026-10-10 16:00', 'UTC');
        $this->assertSame(0.0, TicketReminderService::workingDaysBetween($saturday, $sunday));

        $this->actingAs($this->admin)
            ->post(route('admin.working-calendar.overrides.store'), [
                'date' => '2026-10-10',
                'type' => CalendarOverride::TYPE_WORKING,
                'description' => 'Saturday make-up classes',
            ])
            ->assertRedirect(route('admin.working-calendar', ['month' => '2026-10']));

        $override = CalendarOverride::query()->whereDate('date', '2026-10-10')->firstOrFail();
        $this->assertTrue(TicketReminderService::isWorkingDate($saturday));
        $this->assertSame(1.0, TicketReminderService::workingDaysBetween($saturday, $sunday));
        $this->assertSame($this->admin->id, $override->created_by);
        $this->travelTo($saturday);
        $ticket = $this->ticket();
        $this->travelTo($sunday);
        $this->assertSame(1, $ticket->fresh()->daysOpen());
        $this->assertDatabaseHas('audit_logs', [
            'ticket_id' => null,
            'performed_by' => $this->admin->id,
            'action' => 'calendar_override_created',
            'details' => 'Set working override for 2026-10-10. Description: Saturday make-up classes',
        ]);

        $this->actingAs($this->admin)
            ->delete(route('admin.working-calendar.overrides.destroy', $override))
            ->assertRedirect(route('admin.working-calendar', ['month' => '2026-10']));
        $this->assertSame(0.0, TicketReminderService::workingDaysBetween($saturday, $sunday));
        $this->assertDatabaseHas('audit_logs', [
            'ticket_id' => null,
            'performed_by' => $this->admin->id,
            'action' => 'calendar_override_removed',
            'details' => 'Removed working override for 2026-10-10. Description: Saturday make-up classes',
        ]);
    }

    public function test_range_replaces_existing_overrides_with_one_audit_entry_and_rejects_more_than_sixty_days(): void
    {
        CalendarOverride::create([
            'date' => '2026-10-12',
            'type' => CalendarOverride::TYPE_WORKING,
            'description' => 'Earlier make-up plan',
            'created_by' => $this->admin->id,
        ]);

        $this->actingAs($this->admin)
            ->post(route('admin.working-calendar.ranges.store'), [
                'start_date' => '2026-10-11',
                'end_date' => '2026-10-13',
                'type' => CalendarOverride::TYPE_NON_WORKING,
                'description' => 'Campus suspension',
            ])
            ->assertRedirect(route('admin.working-calendar', ['month' => '2026-10']));

        $overrides = CalendarOverride::query()->orderBy('date')->get();
        $this->assertCount(3, $overrides);
        $this->assertSame(['2026-10-11', '2026-10-12', '2026-10-13'], $overrides->map(fn ($row) => $row->date->toDateString())->all());
        $this->assertSame(3, $overrides->where('type', CalendarOverride::TYPE_NON_WORKING)->count());
        $this->assertSame(['Campus suspension'], $overrides->pluck('description')->unique()->values()->all());
        $this->assertSame(0, AuditLog::query()->where('action', 'calendar_override_created')->count());
        $this->assertSame(0, AuditLog::query()->where('action', 'calendar_override_updated')->count());
        $this->assertSame(1, AuditLog::query()->where('action', 'calendar_override_range_saved')->count());
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'calendar_override_range_saved',
            'performed_by' => $this->admin->id,
            'details' => 'Set non_working overrides for 2026-10-11 through 2026-10-13 (3 dates changed). Description: Campus suspension',
        ]);

        $this->from(route('admin.working-calendar'))
            ->actingAs($this->admin)
            ->post(route('admin.working-calendar.ranges.store'), [
                'start_date' => '2026-10-01',
                'end_date' => '2026-11-30',
                'type' => CalendarOverride::TYPE_NON_WORKING,
                'description' => 'Too long',
            ])
            ->assertSessionHasErrors('end_date');
        $this->assertSame(3, CalendarOverride::count());
        $this->assertSame(1, AuditLog::query()->whereIn('action', [
            'calendar_override_created',
            'calendar_override_updated',
            'calendar_override_range_saved',
        ])->count());
    }

    public function test_working_calendar_routes_are_forbidden_to_students_and_recipients(): void
    {
        $override = CalendarOverride::create([
            'date' => '2026-10-10',
            'type' => CalendarOverride::TYPE_WORKING,
            'description' => 'Make-up day',
            'created_by' => $this->admin->id,
        ]);
        $routes = [
            fn () => $this->get(route('admin.working-calendar')),
            fn () => $this->post(route('admin.working-calendar.overrides.store'), [
                'date' => '2026-10-12',
                'type' => CalendarOverride::TYPE_NON_WORKING,
                'description' => 'Holiday',
            ]),
            fn () => $this->post(route('admin.working-calendar.ranges.store'), [
                'start_date' => '2026-10-12',
                'end_date' => '2026-10-13',
                'type' => CalendarOverride::TYPE_NON_WORKING,
                'description' => 'Break',
            ]),
            fn () => $this->delete(route('admin.working-calendar.overrides.destroy', $override)),
        ];

        foreach ([$this->student, $this->recipient] as $user) {
            foreach ($routes as $request) {
                $this->actingAs($user);
                $request()->assertForbidden();
            }
        }

        $this->assertSame(1, CalendarOverride::count());
    }

    public function test_admin_working_calendar_page_shows_month_navigation_legend_and_override_list(): void
    {
        CalendarOverride::create([
            'date' => '2026-10-12',
            'type' => CalendarOverride::TYPE_NON_WORKING,
            'description' => 'Campus holiday',
            'created_by' => $this->admin->id,
        ]);

        $this->actingAs($this->admin)
            ->get(route('admin.working-calendar', ['month' => '2026-10']))
            ->assertOk()
            ->assertSee('October 2026')
            ->assertSee('Previous month')
            ->assertSee('Next month')
            ->assertSee('Today')
            ->assertSee('Weekend (default non-working)')
            ->assertSee('Non-working override')
            ->assertSee('Working override')
            ->assertSee('Campus holiday')
            ->assertSee('Mark a range');
    }

    public function test_working_calendar_collapses_the_range_form_and_limits_the_initial_override_list(): void
    {
        foreach (range(12, 15) as $day) {
            CalendarOverride::create([
                'date' => "2026-10-{$day}",
                'type' => CalendarOverride::TYPE_NON_WORKING,
                'description' => "Calendar event {$day}",
                'created_by' => $this->admin->id,
            ]);
        }

        $this->actingAs($this->admin)
            ->get(route('admin.working-calendar', ['month' => '2026-10']))
            ->assertOk()
            ->assertSee('Collapse Mark a range')
            ->assertSee('x-collapse', false)
            ->assertSee('See all overrides (4)')
            ->assertSee('Show less');
    }

    private function user(string $role): User
    {
        return User::factory()->create([
            'role' => $role,
            'must_change_password' => false,
            'is_active' => true,
        ]);
    }

    private function ticket(): Ticket
    {
        $complaint = Complaint::create([
            'reference_number' => Complaint::generateReferenceNumber(),
            'student_id' => $this->student->student->id,
            'category_id' => $this->category->id,
            'subject_title' => 'Working Calendar test ticket',
            'description' => 'Ticket for reminder threshold test.',
            'is_anonymous' => false,
            'status' => Ticket::STATUS_SUBMITTED,
        ]);

        return Ticket::create([
            'complaint_id' => $complaint->id,
            'status' => Ticket::STATUS_SUBMITTED,
        ]);
    }
}
