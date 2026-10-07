<?php

namespace Tests\Feature\Admin;

use App\Models\Recipient;
use App\Models\Unit;
use App\Models\User;
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

    public function test_the_page_refreshes_only_the_tables_after_an_account_action(): void
    {
        $this->actingAs($this->admin)->get(route('admin.accounts.index'))
            ->assertOk()
            ->assertSee('#edit-recipient-account-form', false)
            ->assertSee('form[action*="/reactivate"]', false)
            ->assertSee('loadAccountResults(window.location.href, false)', false);
    }
}
