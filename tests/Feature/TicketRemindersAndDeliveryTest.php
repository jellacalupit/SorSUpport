<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Complaint;
use App\Models\ComplaintCategory;
use App\Models\EmailNotification;
use App\Models\Student;
use App\Models\Ticket;
use App\Models\User;
use App\Services\EmailNotificationService;
use App\Services\TicketReminderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class TicketRemindersAndDeliveryTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Every test runs on Wednesday, October 14, 2026 at 10:00, so working days are predictable.
     */
    private const NOW = '2026-10-14 10:00';

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse(self::NOW));
    }

    // ------------------------------------------------------------ email retries

    public function test_a_failed_email_is_tried_again_later_instead_of_given_up(): void
    {
        Mail::shouldReceive('send')->once()->andThrow(new \RuntimeException('Mail server unavailable'));

        $notification = $this->pendingEmail($this->ticket());

        $this->assertSame(0, app(EmailNotificationService::class)->sendPendingNotifications());

        $notification->refresh();
        $this->assertSame(EmailNotification::STATUS_PENDING, $notification->status);
        $this->assertSame(1, $notification->attempts);
        $this->assertTrue($notification->next_attempt_at->isFuture());

        // Not tried again before its time.
        $this->assertSame(0, app(EmailNotificationService::class)->sendPendingNotifications());
        $this->assertSame(1, $notification->fresh()->attempts);
    }

    public function test_an_email_that_keeps_failing_is_given_up_and_recorded_in_the_audit_trail(): void
    {
        Mail::shouldReceive('send')->andThrow(new \RuntimeException('Mail server unavailable'));

        $notification = $this->pendingEmail($this->ticket(), 'office@example.com');

        for ($try = 1; $try <= EmailNotification::MAX_ATTEMPTS; $try++) {
            // Each retry comes due.
            EmailNotification::whereKey($notification->id)->update(['next_attempt_at' => now()->subMinute()]);
            app(EmailNotificationService::class)->sendPendingNotifications();
        }

        $notification->refresh();
        $this->assertSame(EmailNotification::STATUS_FAILED, $notification->status);
        $this->assertSame(EmailNotification::MAX_ATTEMPTS, $notification->attempts);
        $this->assertDatabaseHas('audit_logs', [
            'ticket_id' => $notification->ticket_id,
            'action' => 'email_notification_failed',
            'details' => 'Email to office@example.com could not be sent after 5 tries. Please reach them another way.',
        ]);
    }

    public function test_a_failed_email_entry_hides_a_hidden_identity_students_address(): void
    {
        Mail::shouldReceive('send')->andThrow(new \RuntimeException('Mail server unavailable'));

        $ticket = $this->ticket(anonymous: true);
        $notification = $this->pendingEmail($ticket, $ticket->complaint->student->user->email);
        $notification->update(['attempts' => EmailNotification::MAX_ATTEMPTS - 1]);

        app(EmailNotificationService::class)->sendPendingNotifications();

        $details = AuditLog::query()->where('action', 'email_notification_failed')->value('details');
        $this->assertStringContainsString('Email to the student could not be sent', $details);
    }

    public function test_a_failed_email_does_not_count_as_an_action_on_the_ticket(): void
    {
        $ticket = $this->at('2026-10-11 10:00', fn () => $this->ticket());

        AuditLog::create(['ticket_id' => $ticket->id, 'performed_by' => null, 'action' => 'email_notification_failed', 'details' => 'x']);

        $this->assertSame(3, $ticket->fresh()->daysSinceLastAction());
    }

    // ------------------------------------------------------------ working days

    public function test_working_days_leave_out_weekends(): void
    {
        $days = fn (string $from, string $to) => TicketReminderService::workingDaysBetween(Carbon::parse($from), Carbon::parse($to));

        $this->assertEqualsWithDelta(1.0, $days('2026-10-12 10:00', '2026-10-13 10:00'), 0.001); // Monday to Tuesday
        $this->assertEqualsWithDelta(1.0, $days('2026-10-09 10:00', '2026-10-12 10:00'), 0.001); // Friday to Monday
        $this->assertEqualsWithDelta(0.0, $days('2026-10-10 09:00', '2026-10-11 18:00'), 0.001); // Saturday to Sunday
    }

    // ------------------------------------------------------------ waiting reminders

    public function test_the_sds_office_is_reminded_of_a_ticket_waiting_for_review(): void
    {
        $admin = $this->admin();
        $ticket = $this->at('2026-10-12 08:00', fn () => $this->ticket());

        $this->assertSame(1, app(TicketReminderService::class)->queueReminders());

        $this->assertDatabaseHas('email_notifications', [
            'ticket_id' => $ticket->id,
            'recipient_email' => $admin->email,
            'type' => EmailNotification::TYPE_WAITING_REMINDER,
        ]);
    }

    public function test_no_reminder_while_the_ticket_is_within_its_allowed_days(): void
    {
        $this->admin();
        $this->at('2026-10-13 09:00', fn () => $this->ticket());

        $this->assertSame(0, app(TicketReminderService::class)->queueReminders());
    }

    public function test_a_weekend_does_not_count_toward_the_wait(): void
    {
        $this->admin();
        $this->at('2026-10-09 10:00', fn () => $this->ticket()); // Friday

        $this->travelTo(Carbon::parse('2026-10-12 09:00')); // Monday: three days later, but less than one working day

        $this->assertSame(0, app(TicketReminderService::class)->queueReminders());
    }

    public function test_a_sensitive_ticket_is_reminded_after_one_working_day(): void
    {
        $this->admin();
        $sensitive = $this->at('2026-10-13 09:00', fn () => $this->ticket(sensitive: true));
        $this->at('2026-10-13 09:00', fn () => $this->ticket());

        $this->assertSame(1, app(TicketReminderService::class)->queueReminders());
        $this->assertDatabaseHas('email_notifications', ['ticket_id' => $sensitive->id, 'type' => EmailNotification::TYPE_WAITING_REMINDER]);
    }

    public function test_the_handler_is_reminded_of_a_ticket_not_yet_acknowledged(): void
    {
        $admin = $this->admin();
        $handler = User::factory()->create(['role' => User::ROLE_RECIPIENT]);
        $ticket = $this->at('2026-10-12 08:00', fn () => $this->ticket([
            'status' => Ticket::STATUS_ASSIGNED,
            'assigned_to' => $handler->id,
            'current_handler_id' => $handler->id,
        ]));

        app(TicketReminderService::class)->queueReminders();

        $this->assertDatabaseHas('email_notifications', ['ticket_id' => $ticket->id, 'recipient_email' => $handler->email, 'type' => EmailNotification::TYPE_WAITING_REMINDER]);
        $this->assertDatabaseMissing('email_notifications', ['recipient_email' => $admin->email]);
    }

    public function test_the_handler_is_reminded_when_nothing_is_recorded_for_too_long(): void
    {
        $handler = User::factory()->create(['role' => User::ROLE_RECIPIENT]);
        $ticket = $this->at('2026-10-06 09:00', fn () => $this->ticket([
            'status' => Ticket::STATUS_IN_PROGRESS,
            'assigned_to' => $handler->id,
            'current_handler_id' => $handler->id,
        ]));

        app(TicketReminderService::class)->queueReminders();

        $this->assertDatabaseHas('email_notifications', ['ticket_id' => $ticket->id, 'recipient_email' => $handler->email]);
    }

    public function test_the_sds_office_is_reminded_to_follow_up_a_referral_after_ten_working_days(): void
    {
        $admin = $this->admin();
        $handler = User::factory()->create(['role' => User::ROLE_RECIPIENT]);
        $late = $this->at('2026-09-29 09:00', fn () => $this->ticket([
            'status' => Ticket::STATUS_REFERRED,
            'referred_to' => Ticket::REFERRAL_DESTINATION_CODI,
            'is_codi_referral' => true,
            'current_handler_id' => $handler->id,
        ]));
        $this->at('2026-10-01 09:00', fn () => $this->ticket(['status' => Ticket::STATUS_REFERRED]));

        $this->assertSame(1, app(TicketReminderService::class)->queueReminders());
        $this->assertDatabaseHas('email_notifications', ['ticket_id' => $late->id, 'recipient_email' => $admin->email]);
        $this->assertDatabaseMissing('email_notifications', ['recipient_email' => $handler->email]);
    }

    public function test_a_non_codi_referral_does_not_get_a_reminder_after_ten_working_days_by_default(): void
    {
        $this->admin();
        $ticket = $this->at('2026-09-29 09:00', fn () => $this->ticket([
            'status' => Ticket::STATUS_REFERRED,
            'referred_to' => 'Campus Disciplinary Committee',
        ]));

        $this->assertFalse(app(TicketReminderService::class)->settings()['generic_referral_enabled']);
        $this->assertSame(0, app(TicketReminderService::class)->queueReminders());
        $this->assertDatabaseMissing('email_notifications', ['ticket_id' => $ticket->id, 'type' => EmailNotification::TYPE_WAITING_REMINDER]);
    }

    public function test_the_generic_referral_reminder_can_be_enabled_for_non_codi_referrals(): void
    {
        $admin = $this->admin();
        $ticket = $this->at('2026-09-29 09:00', fn () => $this->ticket([
            'status' => Ticket::STATUS_REFERRED,
            'referred_to' => 'Campus Disciplinary Committee',
        ]));
        $service = app(TicketReminderService::class);

        $this->assertSame(0, $service->queueReminders());
        $service->saveSettings(['generic_referral_enabled' => true, 'generic_referral_days' => 10]);

        $this->assertSame(1, $service->queueReminders());
        $this->assertDatabaseHas('email_notifications', [
            'ticket_id' => $ticket->id,
            'recipient_email' => $admin->email,
            'type' => EmailNotification::TYPE_WAITING_REMINDER,
        ]);
    }

    public function test_the_sds_office_is_told_once_when_the_student_does_not_respond(): void
    {
        $admin = $this->admin();
        $unanswered = $this->ticket(['status' => Ticket::STATUS_RESOLVED, 'resolved_at' => now()->subDays(Ticket::furtherActionDays() + 1)]);
        $this->ticket(['status' => Ticket::STATUS_RESOLVED, 'resolved_at' => now()->subDays(Ticket::furtherActionDays() - 1)]);
        $service = app(TicketReminderService::class);

        $this->assertSame(1, $service->queueReminders());
        $this->assertDatabaseHas('email_notifications', ['ticket_id' => $unanswered->id, 'recipient_email' => $admin->email]);

        // Days later the first ticket is not reminded about again.
        $this->travel(5)->days();
        $service->queueReminders();
        $this->assertSame(1, EmailNotification::query()->where('ticket_id', $unanswered->id)->count());
    }

    public function test_a_recent_action_resets_the_wait(): void
    {
        $this->admin();
        $ticket = $this->at('2026-10-12 08:00', fn () => $this->ticket());

        AuditLog::log($ticket->id, 'ticket_details_updated', null, 'Category corrected.');

        $this->assertSame(0, app(TicketReminderService::class)->queueReminders());
    }

    public function test_a_reminder_repeats_only_after_another_waiting_period(): void
    {
        $this->admin();
        $this->at('2026-10-12 08:00', fn () => $this->ticket());
        $service = app(TicketReminderService::class);

        $this->assertSame(1, $service->queueReminders());
        $this->assertSame(0, $service->queueReminders());

        $this->travelTo(Carbon::parse('2026-10-15 10:00'));
        $this->assertSame(0, $service->queueReminders());

        $this->travelTo(Carbon::parse('2026-10-16 10:30'));
        $this->assertSame(1, $service->queueReminders());
    }

    public function test_clarification_reminders_repeat_on_working_days_and_restart_after_an_action(): void
    {
        $this->admin();
        $ticket = $this->at('2026-10-07 10:00', fn () => $this->ticket([
            'status' => Ticket::STATUS_NEEDS_CLARIFICATION,
            'clarification_requested_at' => now(),
        ]));
        $original = $ticket->only(['status', 'assigned_to', 'current_handler_id']);
        $service = app(TicketReminderService::class);

        $this->travelTo(Carbon::parse('2026-10-13 10:00'));
        $this->assertSame(0, $service->queueReminders());

        $this->travelTo(Carbon::parse(self::NOW));
        $this->assertSame(1, $service->queueReminders());
        $this->assertDatabaseHas('email_notifications', [
            'ticket_id' => $ticket->id,
            'recipient_email' => $ticket->complaint->student->user->email,
            'type' => EmailNotification::TYPE_CLARIFICATION_REMINDER,
            'status' => EmailNotification::STATUS_PENDING,
        ]);
        $this->assertSame(0, $service->queueReminders());

        $this->travelTo(Carbon::parse('2026-10-21 10:00'));
        $this->assertSame(1, $service->queueReminders());
        $this->assertSame(2, $ticket->emailNotifications()->where('type', EmailNotification::TYPE_CLARIFICATION_REMINDER)->count());

        AuditLog::log($ticket->id, 'ticket_details_updated', null, 'SDS updated the ticket.');
        $this->assertSame(0, $service->queueReminders());
        $this->travelTo(Carbon::parse('2026-10-27 10:00'));
        $this->assertSame(0, $service->queueReminders());
        $this->travelTo(Carbon::parse('2026-10-28 10:00'));
        $this->assertSame(1, $service->queueReminders());

        $this->assertSame($original, $ticket->fresh()->only(['status', 'assigned_to', 'current_handler_id']));
        $this->assertSame(3, $ticket->emailNotifications()->where('type', EmailNotification::TYPE_CLARIFICATION_REMINDER)->count());
    }

    public function test_clarification_reminders_stop_after_the_student_replies_and_do_not_apply_to_other_statuses(): void
    {
        $this->admin();
        $clarification = $this->at('2026-10-01 10:00', fn () => $this->ticket([
            'status' => Ticket::STATUS_NEEDS_CLARIFICATION,
            'clarification_requested_at' => now(),
        ]));
        app(\App\Services\TicketWorkflow::class)->provideClarification($clarification, $clarification->complaint->student->user);

        $otherStatusTickets = [];
        foreach ([
            Ticket::STATUS_SUBMITTED,
            Ticket::STATUS_ASSIGNED,
            Ticket::STATUS_IN_PROGRESS,
            Ticket::STATUS_ESCALATED,
            Ticket::STATUS_REFERRED,
            Ticket::STATUS_RESOLVED,
            Ticket::STATUS_CLOSED,
        ] as $status) {
            $otherStatusTickets[] = $this->ticket([
                'status' => $status,
                'clarification_requested_at' => now()->subDays(10),
            ]);
        }

        app(TicketReminderService::class)->queueReminders();

        $this->assertDatabaseMissing('email_notifications', [
            'ticket_id' => $clarification->id,
            'type' => EmailNotification::TYPE_CLARIFICATION_REMINDER,
        ]);
        $this->assertDatabaseMissing('email_notifications', [
            'ticket_id' => $clarification->id,
            'type' => EmailNotification::TYPE_CLARIFICATION_NO_RESPONSE,
        ]);
        $this->assertSame(Ticket::STATUS_SUBMITTED, $clarification->fresh()->status);
        foreach ($otherStatusTickets as $ticket) {
            $this->assertDatabaseMissing('email_notifications', [
                'ticket_id' => $ticket->id,
                'type' => EmailNotification::TYPE_CLARIFICATION_REMINDER,
            ]);
            $this->assertDatabaseMissing('email_notifications', [
                'ticket_id' => $ticket->id,
                'type' => EmailNotification::TYPE_CLARIFICATION_NO_RESPONSE,
            ]);
        }
    }

    public function test_sds_is_notified_once_after_twice_the_clarification_threshold(): void
    {
        $admin = $this->admin();
        $secondAdmin = User::factory()->create(['role' => User::ROLE_SDS_ADMIN]);
        $inactiveAdmin = User::factory()->create(['role' => User::ROLE_SDS_ADMIN, 'is_active' => false]);
        $ticket = $this->at('2026-10-01 10:00', fn () => $this->ticket([
            'status' => Ticket::STATUS_NEEDS_CLARIFICATION,
            'clarification_requested_at' => now(),
        ]));
        $service = app(TicketReminderService::class);

        $this->assertSame(1, $service->queueReminders());
        $this->assertSame(0, $ticket->emailNotifications()->where('type', EmailNotification::TYPE_CLARIFICATION_NO_RESPONSE)->count());

        $this->travelTo(Carbon::parse('2026-10-15 10:00'));
        $this->assertSame(1, $service->queueReminders());
        $this->assertDatabaseHas('email_notifications', [
            'ticket_id' => $ticket->id,
            'recipient_email' => $admin->email,
            'type' => EmailNotification::TYPE_CLARIFICATION_NO_RESPONSE,
        ]);
        $this->assertDatabaseHas('email_notifications', [
            'ticket_id' => $ticket->id,
            'recipient_email' => $secondAdmin->email,
            'type' => EmailNotification::TYPE_CLARIFICATION_NO_RESPONSE,
        ]);
        $this->assertDatabaseMissing('email_notifications', [
            'ticket_id' => $ticket->id,
            'recipient_email' => $inactiveAdmin->email,
            'type' => EmailNotification::TYPE_CLARIFICATION_NO_RESPONSE,
        ]);
        $this->assertSame(0, $service->queueReminders());

        $this->travelTo(Carbon::parse('2026-10-22 10:00'));
        $this->assertSame(1, $service->queueReminders());
        $this->assertSame(2, $ticket->emailNotifications()->where('type', EmailNotification::TYPE_CLARIFICATION_NO_RESPONSE)->count());
    }

    public function test_clarification_emails_are_normal_priority_and_reveal_only_reference_and_safe_subject(): void
    {
        config(['mail.default' => 'array']);
        $this->admin();
        $ticket = $this->at('2026-10-01 10:00', fn () => $this->ticket([
            'status' => Ticket::STATUS_NEEDS_CLARIFICATION,
            'clarification_requested_at' => now(),
        ], anonymous: true, sensitive: true));
        $ticket->complaint->student->update(['student_id' => 'PRIVATE-STUDENT-ID']);
        $ticket->complaint->update([
            'subject_title' => 'Sensitive subject that must not appear',
            'description' => 'Sensitive description that must not appear',
            'personnel_involved' => 'Sensitive personnel name that must not appear',
        ]);
        $this->travelTo(Carbon::parse('2026-10-15 10:00'));

        $this->assertSame(1, app(TicketReminderService::class)->queueReminders());
        $notifications = $ticket->emailNotifications()->get();
        $this->assertCount(2, $notifications);

        foreach ($notifications as $notification) {
            $email = app(\App\Services\TicketEmailComposer::class)->compose($notification);
            $studentUser = $ticket->complaint->student->user;
            $text = implode(' ', [
                $email['subject'],
                $email['heading'],
                ...$email['lines'],
                ...array_values($email['details']),
                $email['body'],
            ]);

            $this->assertStringContainsString($ticket->complaint->reference_number, $text);
            $this->assertStringContainsString('Confidential concern', $text);
            $this->assertStringNotContainsString('Sensitive subject that must not appear', $text);
            $this->assertStringNotContainsString('Sensitive description that must not appear', $text);
            $this->assertStringNotContainsString('Sensitive personnel name that must not appear', $text);
            $this->assertStringNotContainsString($studentUser->name, $text);
            $this->assertStringNotContainsString($ticket->complaint->student->student_id, $text);
            $this->assertSame(['Ticket', 'Subject'], array_keys($email['details']));
        }

        app(EmailNotificationService::class)->sendPendingNotifications();
        $messages = app('mailer')->getSymfonyTransport()->messages();
        $this->assertCount(2, $messages);
        foreach ($messages as $message) {
            $email = $message->getOriginalMessage();
            $html = $email->getHtmlBody();
            $this->assertNotSame(1, $email->getPriority());
            $this->assertNull($email->getHeaders()->get('Importance'));
            $this->assertIsString($html);
            $this->assertStringNotContainsString('Sensitive subject that must not appear', $html);
            $this->assertStringNotContainsString('Sensitive description that must not appear', $html);
            $this->assertStringNotContainsString($ticket->complaint->student->user->name, $html);
            $this->assertStringNotContainsString($ticket->complaint->student->student_id, $html);
        }

        $this->assertSame(Ticket::STATUS_NEEDS_CLARIFICATION, $ticket->fresh()->status);
    }

    public function test_a_reminder_says_how_long_the_ticket_waited_and_is_marked_important(): void
    {
        config(['mail.default' => 'array']);
        $admin = $this->admin();
        $ticket = $this->at('2026-10-12 08:00', fn () => $this->ticket());

        app(TicketReminderService::class)->queueReminders();
        app(EmailNotificationService::class)->sendPendingNotifications();

        $email = app('mailer')->getSymfonyTransport()->messages()->first()->getOriginalMessage();

        $this->assertSame($admin->email, $email->getTo()[0]->getAddress());
        $this->assertSame("Action needed: {$ticket->complaint->reference_number} has waited 2 working days for review", $email->getSubject());
        $this->assertSame(1, $email->getPriority());
        $this->assertSame('High', $email->getHeaders()->get('Importance')?->getBodyAsString());
    }

    // ------------------------------------------------------------ reminder settings

    public function test_reminders_can_be_turned_off(): void
    {
        $this->admin();
        $this->at('2026-10-12 08:00', fn () => $this->ticket());
        $clarification = $this->at('2026-10-01 08:00', fn () => $this->ticket([
            'status' => Ticket::STATUS_NEEDS_CLARIFICATION,
            'clarification_requested_at' => now(),
        ]));
        $service = app(TicketReminderService::class);

        $service->saveSettings(['enabled' => false]);

        $this->assertSame(0, $service->queueReminders());
        $this->assertSame(0, $clarification->emailNotifications()->count());
    }

    public function test_the_admin_changes_the_waiting_days_in_system_settings(): void
    {
        $admin = $this->admin();
        $this->at('2026-10-12 08:00', fn () => $this->ticket());

        $this->actingAs($admin)->get(route('admin.settings', ['settings_tab' => 'reminders']))
            ->assertOk()
            ->assertSee('Reminders for waiting tickets')
            ->assertSee('System default, derived from the CODI 24-hour notice timeline (Handbook p. 58). Adjustable. Not a handbook deadline for SDS.')
            ->assertSee('10-working-day period stated for CODI referrals only (Handbook pp. 57 to 58).')
            ->assertSee('Generic referral reminder')
            ->assertSee('System default, not a handbook deadline. Off by default; applies to referrals other than CODI.')
            ->assertSee('This response period is a system default, not a handbook deadline, and can be adjusted by the SDS admin.');

        $this->actingAs($admin)
            ->put(route('admin.settings.reminders.update'), [
                'enabled' => '1',
                'unreviewed_days' => 4,
                'sensitive_unreviewed_days' => 1,
                'clarification_days' => 5,
                'unacknowledged_days' => 2,
                'idle_days' => 5,
                'referral_days' => 10,
                'generic_referral_enabled' => '1',
                'generic_referral_days' => 10,
                'student_response_days' => 15,
            ])
            ->assertRedirect(route('admin.settings', ['settings_tab' => 'reminders']))
            ->assertSessionHasNoErrors();

        $this->assertSame(4, app(TicketReminderService::class)->settings()['unreviewed_days']);
        $this->assertTrue(app(TicketReminderService::class)->settings()['generic_referral_enabled']);
        $this->assertSame(0, app(TicketReminderService::class)->queueReminders());
        $this->assertDatabaseHas('audit_logs', ['action' => 'reminder_settings_updated']);
    }

    public function test_waiting_days_must_be_at_least_one(): void
    {
        $this->actingAs($this->admin())
            ->put(route('admin.settings.reminders.update'), [
                'enabled' => '1',
                'unreviewed_days' => 0,
                'sensitive_unreviewed_days' => 1,
                'clarification_days' => 5,
                'unacknowledged_days' => 2,
                'idle_days' => 5,
                'referral_days' => 10,
                'generic_referral_enabled' => '0',
                'generic_referral_days' => 10,
                'student_response_days' => 15,
            ])
            ->assertSessionHasErrors('unreviewed_days');
    }

    public function test_generic_referral_days_keep_the_one_to_thirty_day_validation(): void
    {
        $this->actingAs($this->admin())
            ->put(route('admin.settings.reminders.update'), [
                'enabled' => '1',
                'unreviewed_days' => 2,
                'sensitive_unreviewed_days' => 1,
                'clarification_days' => 5,
                'unacknowledged_days' => 2,
                'idle_days' => 5,
                'referral_days' => 10,
                'generic_referral_enabled' => '1',
                'generic_referral_days' => 31,
                'student_response_days' => 15,
            ])
            ->assertSessionHasErrors('generic_referral_days');
    }

    public function test_clarification_reminder_days_must_be_between_one_and_thirty(): void
    {
        $admin = $this->admin();
        $payload = [
            'enabled' => '1',
            'unreviewed_days' => 2,
            'sensitive_unreviewed_days' => 1,
            'clarification_days' => 5,
            'unacknowledged_days' => 2,
            'idle_days' => 5,
            'referral_days' => 10,
            'generic_referral_enabled' => '0',
            'generic_referral_days' => 10,
            'student_response_days' => 15,
        ];

        $this->actingAs($admin)
            ->put(route('admin.settings.reminders.update'), array_replace($payload, ['clarification_days' => 0]))
            ->assertSessionHasErrors('clarification_days');

        $this->actingAs($admin)
            ->put(route('admin.settings.reminders.update'), array_replace($payload, ['clarification_days' => 31]))
            ->assertSessionHasErrors('clarification_days');

        $this->actingAs($admin)
            ->put(route('admin.settings.reminders.update'), array_replace($payload, ['clarification_days' => 1]))
            ->assertSessionHasNoErrors();
        $this->assertSame(1, app(TicketReminderService::class)->settings()['clarification_days']);

        $this->actingAs($admin)
            ->put(route('admin.settings.reminders.update'), array_replace($payload, ['clarification_days' => 30]))
            ->assertSessionHasNoErrors();
        $this->assertSame(30, app(TicketReminderService::class)->settings()['clarification_days']);
    }

    public function test_the_students_days_to_respond_can_be_changed(): void
    {
        $admin = $this->admin();
        $ticket = $this->ticket(['status' => Ticket::STATUS_RESOLVED, 'resolved_at' => now()->subDays(8)]);
        $service = app(TicketReminderService::class);

        $this->assertSame(Ticket::FURTHER_ACTION_DAYS, Ticket::furtherActionDays());
        $this->assertSame(0, $service->queueReminders());

        $service->saveSettings(['student_response_days' => 7]);

        $this->assertSame(7, Ticket::furtherActionDays());
        $this->assertTrue($ticket->furtherActionDeadline()->isPast());
        $this->assertSame(1, $service->queueReminders());
        $this->assertDatabaseHas('email_notifications', ['ticket_id' => $ticket->id, 'recipient_email' => $admin->email]);
    }

    // ------------------------------------------------------------ submission limit

    public function test_a_student_can_submit_only_a_few_tickets_an_hour(): void
    {
        config(['tickets.submissions_per_hour' => 2]);
        $student = User::factory()->create(['role' => User::ROLE_STUDENT]);
        Student::create(['user_id' => $student->id, 'student_id' => '20240009', 'college' => 'CICT', 'program' => 'BSIT', 'year_level' => '1', 'block' => '1']);
        $category = ComplaintCategory::create(['name' => 'General', 'is_active' => true]);
        $ticket = ['declaration' => '1', 'category_id' => $category->id, 'subject_title' => 'Concern', 'description' => 'Details of the concern.'];

        $this->actingAs($student)->post(route('student.complaints.store'), $ticket)->assertSessionHasNoErrors();
        $this->actingAs($student)->post(route('student.complaints.store'), $ticket)->assertSessionHasNoErrors();
        $this->actingAs($student)->post(route('student.complaints.store'), $ticket)->assertSessionHasErrors('submission');

        $this->assertSame(2, Complaint::count());

        $this->travel(61)->minutes();
        $this->actingAs($student)->post(route('student.complaints.store'), $ticket)->assertSessionHasNoErrors();
        $this->assertSame(3, Complaint::count());
    }

    // ------------------------------------------------------------ helpers

    /**
     * Create something as if it happened at the given moment, then come back to the test's now.
     */
    private function at(string $moment, callable $create): mixed
    {
        $this->travelTo(Carbon::parse($moment));
        $created = $create();
        $this->travelTo(Carbon::parse(self::NOW));

        return $created;
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => User::ROLE_SDS_ADMIN]);
    }

    private function pendingEmail(Ticket $ticket, string $email = 'someone@example.com'): EmailNotification
    {
        return EmailNotification::create([
            'ticket_id' => $ticket->id,
            'recipient_email' => $email,
            'type' => EmailNotification::TYPE_STATUS_UPDATE,
            'status' => EmailNotification::STATUS_PENDING,
        ]);
    }

    private function ticket(array $attributes = [], bool $anonymous = false, bool $sensitive = false): Ticket
    {
        $user = User::factory()->create(['role' => User::ROLE_STUDENT]);
        $student = Student::create(['user_id' => $user->id, 'student_id' => (string) $user->id, 'college' => 'CICT', 'program' => 'BSIT', 'year_level' => '1', 'block' => '1']);
        $category = $sensitive
            ? ComplaintCategory::firstOrCreate(['name' => 'Harassment'], ['is_active' => true, 'is_sensitive' => true])
            : ComplaintCategory::firstOrCreate(['name' => 'General'], ['is_active' => true]);

        $complaint = Complaint::create([
            'reference_number' => Complaint::generateReferenceNumber(),
            'student_id' => $student->id,
            'category_id' => $category->id,
            'subject_title' => 'Waiting ticket',
            'description' => 'Testing reminders.',
            'is_anonymous' => $anonymous,
            'status' => Complaint::STATUS_SUBMITTED,
        ]);

        return Ticket::create($attributes + ['complaint_id' => $complaint->id, 'status' => Ticket::STATUS_SUBMITTED]);
    }
}
