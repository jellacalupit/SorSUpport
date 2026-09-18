<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_page_is_displayed(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->get('/profile');

        $response->assertOk();
    }

    public function test_profile_information_can_be_updated(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->patch('/profile', [
                'name' => 'Test User',
                'email' => 'test@example.com',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/profile');

        $user->refresh();

        $this->assertSame('Test User', $user->name);
        $this->assertSame('test@example.com', $user->email);
        $this->assertNull($user->email_verified_at);
    }

    public function test_sds_admin_can_edit_the_admin_profile_details(): void
    {
        $user = User::factory()->create([
            'role' => User::ROLE_SDS_ADMIN,
            'username' => '1001',
        ]);

        $this->actingAs($user)
            ->get('/profile')
            ->assertOk()
            ->assertSee('profile-username')
            ->assertSee('First Name *')
            ->assertSee('Last Name *')
            ->assertSee('Email *')
            ->assertSee('Staff ID *')
            ->assertSee('Department *')
            ->assertSee('Position *');

        $response = $this
            ->actingAs($user)
            ->patch('/profile', [
                'first_name' => 'Updated',
                'middle_name' => 'SDS',
                'last_name' => 'Admin',
                'extension' => 'Sr.',
                'email' => 'updated-admin@example.com',
                'username' => '1002',
                'department' => 'Student Development Services',
                'designation' => 'Administrator',
                'role' => User::ROLE_SDS_ADMIN,
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/profile');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'Updated S. Admin Sr.',
            'email' => 'updated-admin@example.com',
            'username' => '1002',
            'department' => 'Student Development Services',
            'designation' => 'Administrator',
        ]);
    }

    public function test_email_verification_status_is_unchanged_when_the_email_address_is_unchanged(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->patch('/profile', [
                'name' => 'Test User',
                'email' => $user->email,
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/profile');

        $this->assertNotNull($user->refresh()->email_verified_at);
    }

    public function test_user_can_delete_their_account(): void
    {
        $user = User::factory()->create();
        $deletedUserName = $user->name;

        $response = $this
            ->actingAs($user)
            ->delete('/profile', [
                'password' => 'password',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/');

        $this->assertGuest();
        $this->assertNull($user->fresh());
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'account_deleted',
            'performed_by' => null,
            'details' => 'Deleted account for ' . $deletedUserName . '.',
        ]);
    }

    public function test_correct_password_must_be_provided_to_delete_account(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->from('/profile')
            ->delete('/profile', [
                'password' => 'wrong-password',
            ]);

        $response
            ->assertSessionHasErrorsIn('userDeletion', 'password')
            ->assertRedirect('/profile');

        $this->assertNotNull($user->fresh());
    }
}
