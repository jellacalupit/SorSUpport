<?php

namespace Tests\Feature\Admin;

use App\Models\Recipient;
use App\Models\Complaint;
use App\Models\ComplaintCategory;
use App\Models\Student;
use App\Models\Ticket;
use App\Models\Unit;
use App\Models\User;
use App\Services\TicketWorkflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountsPageTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Unit::create(['type' => Unit::TYPE_OFFICE, 'name' => 'Registrar']);

        $this->admin = User::factory()->create([
            'role' => User::ROLE_SDS_ADMIN,
            'is_active' => true,
            'email_verified_at' => now(),
            'must_change_password' => false,
        ]);
    }

    protected function recipients(int $count): void
    {
        foreach (range(1, $count) as $number) {
            $user = User::factory()->create(['role' => User::ROLE_RECIPIENT, 'username' => (string) (250000 + $number)]);
            Recipient::create(['user_id' => $user->id, 'staff_id' => (string) (250000 + $number), 'unit' => 'Registrar', 'designation' => 'Staff']);
        }
    }

    public function test_each_table_says_how_many_accounts_it_shows(): void
    {
        $this->recipients(17);

        $this->actingAs($this->admin)->get(route('admin.accounts.index', ['category_filter' => 'recipients']))
            ->assertOk()
            ->assertSee('Showing 1–15 of', false)
            ->assertSee('recipient accounts')
            ->assertSee('No student accounts to show');

        $this->actingAs($this->admin)->get(route('admin.accounts.index', ['category_filter' => 'recipients', 'recipients_page' => 2]))
            ->assertOk()
            ->assertSee('Showing 16–', false);
    }

    public function test_the_recipient_pages_keep_the_recipients_tab(): void
    {
        $this->recipients(17);

        // The page links sit inside the block that is refreshed in place, and they carry the tab.
        $html = $this->actingAs($this->admin)->get(route('admin.accounts.index', ['category_filter' => 'recipients']))->getContent();
        $block = substr($html, strpos($html, 'data-account-table="recipients"'));

        $this->assertStringContainsString('aria-label="Recipient accounts pagination"', $block);
        $this->assertMatchesRegularExpression('/href="[^"]*category_filter=recipients[^"]*recipients_page=2/', $block);
    }

    public function test_a_rejected_edit_answers_with_the_errors_for_the_form(): void
    {
        $this->recipients(2);
        $first = Recipient::query()->orderBy('id')->first();
        $second = Recipient::query()->orderBy('id')->skip(1)->first();

        $this->actingAs($this->admin)
            ->putJson(route('admin.accounts.update', $second->user), [
                'role' => User::ROLE_RECIPIENT,
                'first_name' => 'Rey',
                'last_name' => 'Registrar',
                'email' => $first->user->email,
                'staff_id' => $second->staff_id,
                'unit' => 'Registrar',
                'designation' => 'Staff',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('email');
    }

    public function test_correcting_an_id_keeps_the_first_time_password_in_step(): void
    {
        $this->recipients(2);
        $first = Recipient::query()->orderBy('id')->first();
        $second = Recipient::query()->orderBy('id')->skip(1)->first();
        $first->user->update(['password' => \Illuminate\Support\Facades\Hash::make($first->staff_id), 'must_change_password' => true]);
        $second->user->update(['password' => \Illuminate\Support\Facades\Hash::make('Their-Own-Pass1'), 'must_change_password' => false]);

        $edit = fn (Recipient $recipient, string $staffId) => $this->actingAs($this->admin)->putJson(route('admin.accounts.update', $recipient->user), [
            'role' => User::ROLE_RECIPIENT,
            'first_name' => 'Rey',
            'last_name' => 'Registrar',
            'email' => $recipient->user->email,
            'staff_id' => $staffId,
            'unit' => 'Registrar',
            'designation' => 'Staff',
        ])->assertSessionHasNoErrors();

        // Still on the first-time password: it follows the corrected ID.
        $edit($first, '260001');
        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('260001', $first->user->fresh()->password));

        // A password the user chose is left alone.
        $edit($second, '260002');
        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('Their-Own-Pass1', $second->user->fresh()->password));
    }

    public function test_first_time_passwords_that_fell_out_of_step_are_repaired(): void
    {
        $this->recipients(2);
        $stuck = Recipient::query()->orderBy('id')->first()->user;
        $own = Recipient::query()->orderBy('id')->skip(1)->first()->user;
        $stuck->update(['password' => \Illuminate\Support\Facades\Hash::make('an-old-id'), 'must_change_password' => true]);
        $own->update(['password' => \Illuminate\Support\Facades\Hash::make('Their-Own-Pass1'), 'must_change_password' => false]);

        (require database_path('migrations/2026_10_10_600000_repair_first_time_passwords.php'))->up();

        $this->assertTrue(\Illuminate\Support\Facades\Hash::check($stuck->username, $stuck->fresh()->password));
        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('Their-Own-Pass1', $own->fresh()->password));
    }

    public function test_the_page_refreshes_only_the_tables_after_an_account_action(): void
    {
        $this->actingAs($this->admin)->get(route('admin.accounts.index'))
            ->assertOk()
            ->assertSee('#edit-recipient-account-form', false)
            ->assertSee('form[action*="/reactivate"]', false)
            ->assertSee('loadAccountResults(window.location.href, false)', false);
    }

    public function test_recipient_deactivation_waits_until_all_open_tickets_are_reassigned(): void
    {
        $recipientUser = User::factory()->create([
            'role' => User::ROLE_RECIPIENT,
            'is_active' => true,
        ]);
        $recipient = Recipient::create([
            'user_id' => $recipientUser->id,
            'staff_id' => 'STAFF-ORPHAN',
            'unit' => 'Registrar',
            'designation' => 'Staff',
        ]);
        $replacement = Recipient::create([
            'user_id' => User::factory()->create(['role' => User::ROLE_RECIPIENT])->id,
            'staff_id' => 'STAFF-REPLACEMENT',
            'unit' => 'Registrar',
            'designation' => 'Staff',
        ]);
        $studentUser = User::factory()->create(['role' => User::ROLE_STUDENT]);
        $student = Student::create([
            'user_id' => $studentUser->id,
            'student_id' => '20249991',
            'college' => 'CICT',
            'program' => 'BSIT',
            'year_level' => '1',
            'block' => '1',
        ]);
        $category = ComplaintCategory::create(['name' => 'General', 'is_active' => true]);
        $tickets = collect([Ticket::STATUS_ASSIGNED, Ticket::STATUS_IN_PROGRESS, Ticket::STATUS_ESCALATED])
            ->map(function (string $status) use ($student, $category, $recipientUser): Ticket {
                $complaint = Complaint::create([
                    'reference_number' => Complaint::generateReferenceNumber(),
                    'student_id' => $student->id,
                    'category_id' => $category->id,
                    'subject_title' => 'Do not show this subject',
                    'description' => 'Ticket description.',
                    'status' => $status,
                ]);

                return Ticket::create([
                    'complaint_id' => $complaint->id,
                    'status' => $status,
                    'classification' => Ticket::CLASSIFICATION_NEEDS_RESOLUTION,
                    'assigned_to' => $recipientUser->id,
                    'current_handler_id' => $recipientUser->id,
                ]);
            });

        $response = $this->actingAs($this->admin)
            ->from(route('admin.accounts.index', ['category_filter' => 'recipients']))
            ->patch(route('admin.accounts.deactivate', $recipientUser))
            ->assertRedirect(route('admin.accounts.index', ['category_filter' => 'recipients']))
            ->assertSessionHasErrors('account');

        $response->assertSessionHas('blocked_deactivation_tickets', function (array $blocked) use ($tickets): bool {
            return count($blocked) === 3
                && collect($blocked)->pluck('reference')->sort()->values()->all()
                    === $tickets->map(fn (Ticket $ticket) => $ticket->complaint->reference_number)->sort()->values()->all();
        });
        $blockedPage = $this->actingAs($this->admin)
            ->get(route('admin.accounts.index', ['category_filter' => 'recipients']))
            ->assertOk()
            ->assertSee('Reassign these open tickets using the Assign action before deactivating this account.')
            ->assertDontSee('Do not show this subject');
        foreach ($tickets as $ticket) {
            $blockedPage->assertSee($ticket->complaint->reference_number)
                ->assertSee(route('admin.complaints.show', $ticket->complaint), false);
            $this->assertDatabaseHas('users', ['id' => $recipientUser->id, 'is_active' => true]);
            $this->assertDatabaseHas('tickets', [
                'id' => $ticket->id,
                'assigned_to' => $recipientUser->id,
                'current_handler_id' => $recipientUser->id,
            ]);
        }

        foreach ($tickets as $ticket) {
            app(TicketWorkflow::class)->assign($ticket, $this->admin, $replacement);
        }

        $this->actingAs($this->admin)
            ->patch(route('admin.accounts.deactivate', $recipientUser))
            ->assertRedirect(route('admin.accounts.index', ['category_filter' => 'recipients']));
        $this->assertDatabaseHas('users', ['id' => $recipientUser->id, 'is_active' => false]);
        foreach ($tickets as $ticket) {
            $this->assertSame($replacement->user_id, $ticket->fresh()->current_handler_id);
            $this->assertSame($replacement->user_id, $ticket->fresh()->assigned_to);
        }
    }

    public function test_admin_removal_anonymizes_accounts_with_ticket_records(): void
    {
        $recipientUser = User::factory()->create(['role' => User::ROLE_RECIPIENT]);
        $recipient = Recipient::create([
            'user_id' => $recipientUser->id,
            'staff_id' => 'STAFF-RETAIN',
            'unit' => 'Registrar',
            'designation' => 'Staff',
        ]);
        $ticket = $this->makeAssignedTicket($recipientUser);
        $oldId = $recipientUser->id;

        $this->actingAs($this->admin)
            ->delete(route('admin.accounts.destroy', $recipientUser))
            ->assertRedirect(route('admin.accounts.index', ['category_filter' => 'recipients']));

        $this->assertDatabaseHas('users', [
            'id' => $oldId,
            'name' => 'Deleted user',
            'username' => "deleted-{$oldId}",
            'email' => "deleted-{$oldId}@deleted.invalid",
            'is_active' => false,
        ]);
        $this->assertDatabaseHas('tickets', ['id' => $ticket->id]);
        $this->assertDatabaseHas('audit_logs', [
            'ticket_id' => null,
            'action' => 'account_anonymized',
            'performed_by' => $this->admin->id,
        ]);
        $this->assertNotNull($recipient);
    }

    private function makeAssignedTicket(User $handler): Ticket
    {
        $studentUser = User::factory()->create(['role' => User::ROLE_STUDENT]);
        $student = Student::create([
            'user_id' => $studentUser->id,
            'student_id' => '20249992',
            'college' => 'CICT',
            'program' => 'BSIT',
            'year_level' => '1',
            'block' => '1',
        ]);
        $category = ComplaintCategory::create(['name' => 'General', 'is_active' => true]);
        $complaint = Complaint::create([
            'reference_number' => Complaint::generateReferenceNumber(),
            'student_id' => $student->id,
            'category_id' => $category->id,
            'subject_title' => 'Concern subject',
            'description' => 'Concern details.',
        ]);

        return Ticket::create([
            'complaint_id' => $complaint->id,
            'status' => Ticket::STATUS_ASSIGNED,
            'classification' => Ticket::CLASSIFICATION_NEEDS_RESOLUTION,
            'assigned_to' => $handler->id,
            'current_handler_id' => $handler->id,
        ]);
    }
}
