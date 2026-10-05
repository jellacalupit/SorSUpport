<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PasswordUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_password_can_be_updated(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->from('/profile')
            ->put('/password', [
                'current_password' => 'password',
                'password' => 'new-password',
                'password_confirmation' => 'new-password',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/student/dashboard');

        $this->assertTrue(Hash::check('new-password', $user->refresh()->password));
    }

    public function test_current_password_check_returns_error_without_changing_password(): void
    {
        $user = User::factory()->create();
        $originalPassword = $user->password;

        $response = $this->actingAs($user)->post(route('password.check-current'), [
            'current_password' => 'wrong-password',
        ]);

        $response->assertStatus(422)
            ->assertJson(['message' => 'Incorrect password. Try again.']);

        $this->assertSame($originalPassword, $user->refresh()->password);
    }

    public function test_current_password_check_allows_valid_password(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('password.check-current'), ['current_password' => 'password'])
            ->assertOk()
            ->assertJson(['valid' => true]);
    }

    public function test_correct_password_must_be_provided_to_update_password(): void
    {
        $user = User::factory()->create();

        $originalPassword = $user->password;

        $response = $this
            ->actingAs($user)
            ->from('/profile')
            ->put('/password', [
                'current_password' => 'wrong-password',
                'password' => 'new-password',
                'password_confirmation' => 'new-password',
            ]);

        $response
            ->assertSessionHasErrorsIn('updatePassword', 'current_password', 'Incorrect password. Try again.')
            ->assertRedirect('/profile');

        $this->get('/profile')
            ->assertOk()
            ->assertSee('Input does not match current password')
            ->assertSee('id="recipient-password-form"', false);

        $this->assertSame($originalPassword, $user->refresh()->password);
    }

    public function test_current_password_is_required_to_update_password(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->from('/profile')
            ->put('/password', [
                'password' => 'new-password',
                'password_confirmation' => 'new-password',
            ]);

        $response
            ->assertSessionHasErrorsIn('updatePassword', 'current_password')
            ->assertRedirect('/profile');
    }
}
