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
            ->assertSee('id="profile-photo-overlay"', false)
            ->assertSee('id="recipient-profile-photo"', false)
            ->assertSeeText('First Name *')
            ->assertSeeText('Last Name *')
            ->assertSeeText('Email *')
            ->assertSeeText('Staff ID *')
            ->assertSeeText('College / Office')
            ->assertSeeText('Position');

        // The office and designation must be ones configured in System Settings.
        \App\Models\Unit::create(['name' => 'Student Development Services', 'type' => \App\Models\Unit::TYPE_OFFICE])
            ->designations()
            ->create(['name' => 'Administrator']);

        $response = $this
            ->actingAs($user)
            ->patch('/profile', [
                'first_name' => 'Updated',
                'middle_name' => 'SDS',
                'last_name' => 'Admin',
                'extension' => 'Sr.',
                'email' => 'updated-admin@example.com',
                'username' => '1002',
                'unit' => 'Student Development Services',
                'designation' => 'Administrator',
                'role' => User::ROLE_SDS_ADMIN,
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/profile');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'Updated SDS Admin Sr.',
            'email' => 'updated-admin@example.com',
            'username' => '1002',
        ]);

        $this->assertDatabaseHas('recipients', [
            'user_id' => $user->id,
            'unit' => 'Student Development Services',
            'designation' => 'Administrator',
        ]);
    }

    public function test_sds_admin_cannot_change_their_own_role_from_the_profile_form(): void
    {
        $admin = User::factory()->create([
            'role' => User::ROLE_SDS_ADMIN,
            'username' => '1001',
        ]);

        $this->actingAs($admin)
            ->patch('/profile', [
                'first_name' => 'Still',
                'last_name' => 'Admin',
                'email' => 'still-admin@example.com',
                'username' => '1001',
                'role' => User::ROLE_STUDENT,
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(User::ROLE_SDS_ADMIN, $admin->fresh()->role);
    }

    public function test_sds_admin_profile_photo_control_starts_hidden_until_edit_mode(): void
    {
        $user = User::factory()->create([
            'role' => User::ROLE_SDS_ADMIN,
            'username' => '1001',
        ]);

        $this->actingAs($user)
            ->get('/profile')
            ->assertOk()
            ->assertSee('id="profile-photo-overlay"', false)
            ->assertSee('id="recipient-profile-photo"', false)
            ->assertSee('class="absolute bottom-1 right-1 z-20 hidden"', false)
            ->assertSee("getElementById('profile-photo-overlay')?.classList.remove('hidden')", false)
            ->assertSee("getElementById('profile-photo-overlay')?.classList.add('hidden')", false);
    }

    public function test_sds_admin_profile_does_not_infer_middle_name_from_full_name(): void
    {
        $user = User::factory()->create([
            'role' => User::ROLE_SDS_ADMIN,
            'name' => 'Mary Jane Smith',
            'first_name' => null,
            'middle_name' => null,
            'last_name' => null,
        ]);

        $this->actingAs($user)
            ->get('/profile')
            ->assertOk()
            ->assertSee('name="first_name" value="Mary Jane"', false)
            ->assertSee('name="middle_name" value=""', false)
            ->assertSee('name="last_name" value="Smith"', false);
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
