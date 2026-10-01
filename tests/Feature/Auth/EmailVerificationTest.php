<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Notifications\VerifyEmailNotification;
use Illuminate\Auth\Events\Verified;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class EmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_account_creation_does_not_send_verification_email_immediately(): void
    {
        Notification::fake();

        $admin = User::factory()->create([
            'role' => User::ROLE_SDS_ADMIN,
            'email_verified_at' => now(),
            'is_active' => true,
            'must_change_password' => false,
        ]);

        $this->actingAs($admin)
            ->post(route('admin.accounts.store'), [
                'first_name' => 'Jane',
                'last_name' => 'Doe',
                'middle_name' => '',
                'email' => 'janedoe@example.com',
                'role' => User::ROLE_STUDENT,
                'student_id' => '12345678',
                'department' => 'CICT',
                'course' => 'BSIT',
                'year_level' => 1,
                'block' => 1,
            ])
            ->assertRedirect(route('admin.accounts.index', ['category_filter' => 'students']));

        Notification::assertNothingSent();
    }

    public function test_inactive_account_stays_on_login_page(): void
    {
        $inactiveUser = User::factory()->create([
            'username' => 'inactive.student',
            'email' => 'inactive.student@example.com',
            'password' => Hash::make('password'),
            'is_active' => false,
            'must_change_password' => false,
            'email_verified_at' => now(),
            'role' => User::ROLE_STUDENT,
        ]);

        $response = $this->from(route('login'))->post(route('login'), [
            'username' => $inactiveUser->username,
            'password' => 'password',
        ]);

        $response->assertRedirect(route('login'));
        $response->assertSessionHasErrors([
            'username' => 'This account is inactive. Please contact Student Development Services.',
        ]);
    }

    public function test_inactive_authenticated_user_is_logged_out_and_sent_to_login_page(): void
    {
        $inactiveUser = User::factory()->create([
            'name' => 'Inactive Student',
            'username' => 'inactive.student.route',
            'email' => 'inactive.student.route@example.com',
            'password' => Hash::make('password'),
            'is_active' => false,
            'must_change_password' => false,
            'email_verified_at' => now(),
            'role' => User::ROLE_STUDENT,
        ]);

        $response = $this->actingAs($inactiveUser)
            ->from(route('student.dashboard'))
            ->get(route('student.dashboard'));

        $response->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_email_verification_screen_can_be_rendered(): void
    {
        $user = User::factory()->unverified()->create(['is_active' => false]);

        $response = $this->actingAs($user)->get('/verify-email');

        $response->assertStatus(200);
    }

    public function test_verification_email_can_be_resent(): void
    {
        Notification::fake();

        $user = User::factory()->unverified()->create(['is_active' => false]);

        $response = $this->actingAs($user)->post(route('verification.send'));

        $response->assertRedirect();
        $response->assertSessionHas('status', 'verification-link-sent');
        Notification::assertSentTo($user, VerifyEmailNotification::class);
    }

    public function test_verification_email_uses_the_clean_project_template(): void
    {
        Notification::fake();

        $user = User::factory()->unverified()->create(['is_active' => false]);
        $user->sendEmailVerificationNotification();

        Notification::assertSentTo($user, VerifyEmailNotification::class, function ($notification) use ($user) {
            $email = $notification->toMail($user)->render();

            return str_contains($email, 'Verify Email Address')
                && ! str_contains($email, "If you're having trouble clicking")
                && ! str_contains($email, '<h1>SorSUpport</h1>');
        });
    }

    public function test_email_can_be_verified(): void
    {
        $user = User::factory()->unverified()->create(['is_active' => false]);

        Event::fake();

        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $user->id, 'hash' => sha1($user->email)]
        );

        $response = $this->actingAs($user)->get($verificationUrl);

        Event::assertDispatched(Verified::class);
        $this->assertTrue($user->fresh()->hasVerifiedEmail());
        $this->assertTrue($user->fresh()->is_active);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'account_activated',
            'performed_by' => $user->id,
        ]);
        $response->assertRedirect(route('student.dashboard', absolute: false));
    }

    public function test_email_link_authenticates_user_before_password_setup(): void
    {
        $user = User::factory()->unverified()->create([
            'is_active' => false,
            'must_change_password' => true,
        ]);

        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $user->id, 'hash' => sha1($user->email)]
        );

        $response = $this->get($verificationUrl);

        $response->assertRedirect(route('password.force', absolute: false));
        $this->assertAuthenticatedAs($user);
        $this->get(route('password.force'))->assertOk()->assertSee('Create Your Password');
    }

    public function test_email_is_not_verified_with_invalid_hash(): void
    {
        $user = User::factory()->unverified()->create();

        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $user->id, 'hash' => sha1('wrong-email')]
        );

        $this->actingAs($user)->get($verificationUrl);

        $this->assertFalse($user->fresh()->hasVerifiedEmail());
    }
}
