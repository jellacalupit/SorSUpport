<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_reset_password_link_screen_can_be_rendered(): void
    {
        $response = $this->get('/forgot-password');

        $response->assertStatus(200);
    }

    public function test_reset_password_link_requires_an_id(): void
    {
        $response = $this->from('/forgot-password')->post('/forgot-password', [
            'username' => '',
        ]);

        $response->assertRedirect('/forgot-password')
            ->assertSessionHasErrors(['username' => 'Student or Staff ID is required.']);
    }

    public function test_reset_password_link_can_be_requested(): void
    {
        Notification::fake();

        $user = User::factory()->create([
            'email' => 'jella@outlook.com',
        ]);

        $response = $this->post('/forgot-password', ['username' => $user->username]);

        $response->assertRedirect(route('password.sent', absolute: false));

        $this->get(route('password.sent'))
            ->assertOk()
            ->assertSee('Email Sent')
            ->assertSee(substr($user->email, 0, 2).'*****@'.substr($user->email, strpos($user->email, '@') + 1));

        Notification::assertSentTo($user, ResetPasswordNotification::class, function ($notification) use ($user) {
            $email = $notification->toMail($user)->render();

            return str_contains($email, 'expire in 15 minutes')
                && ! str_contains($email, "If you're having trouble clicking")
                && str_contains($email, 'branding/sorsu%20logo.png');
        });
    }

    public function test_reset_password_screen_can_be_rendered(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        $this->post('/forgot-password', ['username' => $user->username]);

        Notification::assertSentTo($user, ResetPasswordNotification::class, function ($notification) {
            $response = $this->get('/reset-password/'.$notification->token);

            $response
                ->assertStatus(200)
                ->assertSee('Create Your Password');

            return true;
        });
    }

    public function test_password_can_be_reset_with_valid_token(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        $this->post('/forgot-password', ['username' => $user->username]);

        Notification::assertSentTo($user, ResetPasswordNotification::class, function ($notification) use ($user) {
            $response = $this->post('/reset-password', [
                'token' => $notification->token,
                'email' => $user->email,
                'password' => 'password',
                'password_confirmation' => 'password',
            ]);

            $response
                ->assertSessionHasNoErrors()
                ->assertRedirect(route('login'));

            $this->assertGuest();

            return true;
        });
    }
    public function test_reset_password_link_rejects_unknown_id(): void
    {
        $response = $this->from('/forgot-password')->post('/forgot-password', [
            'username' => '99999999',
        ]);

        $response->assertRedirect('/forgot-password')
            ->assertSessionHasErrors(['username' => 'Incorrect ID. Try again.']);
    }
}
