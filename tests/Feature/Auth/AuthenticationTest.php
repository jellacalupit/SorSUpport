<?php

namespace Tests\Feature\Auth;

use App\Models\Recipient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_can_be_rendered(): void
    {
        $response = $this->get('/login');

        $response->assertStatus(200);
    }

    public function test_login_rejects_empty_credentials(): void
    {
        $response = $this->from('/login')->post('/login', [
            'username' => '',
            'password' => '',
        ]);

        $response->assertRedirect(route('login', absolute: false))
            ->assertSessionHasErrors(['username' => 'Student or Staff ID is required.']);
    }

    public function test_ui_prototype_pages_follow_complaint_flow_without_authentication(): void
    {
        $this->get(route('verification.notice'))->assertOk()->assertSee('Verify Your Email')->assertDontSee('Proceed');
    }

    public function test_each_prototype_account_opens_its_role_view_without_authentication(): void
    {
        $this->get(route('prototype.student.dashboard'))
            ->assertRedirect(route('student.dashboard', absolute: false));
        $this->get(route('student.dashboard'))
            ->assertOk()
            ->assertSee('Welcome back');

        $this->get(route('prototype.admin.dashboard'))
            ->assertRedirect(route('admin.dashboard', absolute: false));
        $this->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Dashboard');

        $this->get(route('prototype.recipient.dashboard'))
            ->assertRedirect(route('recipient.dashboard', absolute: false));
        $this->get(route('recipient.dashboard'))
            ->assertOk()
            ->assertSee('Recent Tickets');
    }

    public function test_users_can_authenticate_using_the_login_screen(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        $response = $this->post('/login', [
            'username' => $user->username,
            'password' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('student.dashboard', absolute: false));

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'user_logged_in',
            'performed_by' => $user->id,
        ]);
    }

    public function test_users_can_not_authenticate_with_their_email_address(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        $response = $this->post('/login', [
            'username' => $user->email,
            'password' => 'password',
        ]);

        $this->assertGuest();
        $response->assertSessionHasErrors(['password' => 'Incorrrect ID or password. Try again.']);
    }

    public function test_unverified_users_are_sent_to_email_verification_after_login(): void
    {
        /** @var User $user */
        $user = User::factory()->create([
            'is_active' => false,
            'email_verified_at' => null,
        ]);

        $response = $this->post('/login', [
            'username' => $user->username,
            'password' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('verification.notice', absolute: false));
    }

    public function test_users_can_not_authenticate_with_invalid_password(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        $response = $this->from('/login')->post('/login', [
            'username' => $user->username,
            'password' => 'wrong-password',
        ]);

        $this->assertGuest();
        $response->assertRedirect(route('login', absolute: false))
            ->assertSessionHasErrors(['password' => 'Incorrrect ID or password. Try again.']);
    }

    public function test_admin_settings_only_exposes_active_recipients_for_category_workflows(): void
    {
        $admin = User::factory()->create([
            'role' => User::ROLE_SDS_ADMIN,
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        $activeRecipientUser = User::factory()->create([
            'role' => User::ROLE_RECIPIENT,
            'name' => 'Active Recipient',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);
        Recipient::create([
            'user_id' => $activeRecipientUser->id,
            'staff_id' => 'R-1001',
            'unit' => 'Student Affairs',
            'designation' => 'Recipient',
        ]);

        $inactiveRecipientUser = User::factory()->create([
            'role' => User::ROLE_RECIPIENT,
            'name' => 'Inactive Recipient',
            'is_active' => false,
            'email_verified_at' => now(),
        ]);
        Recipient::create([
            'user_id' => $inactiveRecipientUser->id,
            'staff_id' => 'R-1002',
            'unit' => 'Student Affairs',
            'designation' => 'Recipient',
        ]);

        $unverifiedRecipientUser = User::factory()->create([
            'role' => User::ROLE_RECIPIENT,
            'name' => 'Unverified Recipient',
            'is_active' => true,
            'email_verified_at' => null,
        ]);
        Recipient::create([
            'user_id' => $unverifiedRecipientUser->id,
            'staff_id' => 'R-1003',
            'unit' => 'Student Affairs',
            'designation' => 'Recipient',
        ]);

        $response = $this->actingAs($admin)
            ->get(route('admin.settings'));

        $response->assertOk()
            ->assertSee('Active Recipient')
            ->assertDontSee('Inactive Recipient')
            ->assertSee('Unverified Recipient');
    }

    public function test_users_can_logout(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/logout');

        $this->assertGuest();
        $response->assertRedirect(route('login', absolute: false));
    }

    public function test_users_can_logout_via_navigation_link(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/logout');

        $this->assertGuest();
        $response->assertRedirect(route('login'));
    }

    public function test_expired_logout_request_redirects_to_login(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/logout', [
            '_token' => 'expired-token',
        ]);

        $this->assertGuest();
        $response->assertRedirect(route('login'));
    }
}
