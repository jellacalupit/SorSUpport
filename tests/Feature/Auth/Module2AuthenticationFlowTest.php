<?php

namespace Tests\Feature\Auth;

use App\Models\Student;
use App\Models\User;
use Database\Seeders\SdsAdminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class Module2AuthenticationFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_user_is_redirected_to_password_change_on_first_login(): void
    {
        Notification::fake();

        /** @var User $user */
        $user = User::factory()->unverified()->create([
            'username' => 'student123',
            'password' => bcrypt('password'),
            'must_change_password' => true,
            'is_active' => false,
        ]);

        $this->get('/login');

        $response = $this->post('/login', [
            'username' => 'student123',
            'password' => 'password',
        ]);

        $response->assertRedirect(route('verification.notice'));
        $this->assertAuthenticatedAs($user);
        Notification::assertSentTo($user, \Illuminate\Auth\Notifications\VerifyEmail::class);
    }

    public function test_sds_admin_seed_requires_profile_setup_on_first_login(): void
    {
        Artisan::call('db:seed', ['--class' => SdsAdminSeeder::class]);

        $this->assertDatabaseHas('users', [
            'username' => '12345',
            'role' => User::ROLE_SDS_ADMIN,
            'must_change_password' => true,
        ]);
    }

    public function test_default_admin_profile_uses_placeholder_details_on_first_login(): void
    {
        $admin = User::factory()->create([
            'username' => '12345',
            'name' => '',
            'first_name' => null,
            'middle_name' => null,
            'last_name' => null,
            'email' => 'sorsu.support@gmail.com',
            'password' => bcrypt('12345'),
            'role' => User::ROLE_SDS_ADMIN,
            'must_change_password' => true,
            'email_verified_at' => now(),
            'is_active' => true,
        ]);

        $admin->recipient()->updateOrCreate(['user_id' => $admin->id], [
            'staff_id' => '2648',
            'department' => 'Student Development Services',
            'designation' => 'SDS Coordinator',
        ]);

        $this->actingAs($admin)
            ->get(route('profile.edit'))
            ->assertOk()
            ->assertSee('Administrator')
            ->assertSee('sorsu.support@gmail.com')
            ->assertSee('ID —')
            ->assertSee('Department')
            ->assertSee('Position')
            ->assertDontSee('2648')
            ->assertDontSee('Student Development Services')
            ->assertDontSee('SDS Coordinator')
            ->assertDontSee('12345');
    }

    public function test_verified_user_redirects_to_role_dashboard(): void
    {
        /** @var User $user */
        $user = User::factory()->create([
            'username' => 'student123',
            'password' => bcrypt('password'),
            'role' => User::ROLE_STUDENT,
            'email_verified_at' => now(),
            'must_change_password' => false,
        ]);

        $response = $this->post('/login', [
            'username' => 'student123',
            'password' => 'password',
        ]);

        $response->assertRedirect(route('student.dashboard'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_student_cannot_access_admin_routes(): void
    {
        /** @var User $user */
        $user = User::factory()->create([
            'username' => 'student123',
            'password' => bcrypt('password'),
            'role' => User::ROLE_STUDENT,
            'email_verified_at' => now(),
            'must_change_password' => false,
        ]);

        $this->actingAs($user)
            ->get(route('admin.dashboard'))
            ->assertStatus(403);
    }

    public function test_admin_can_navigate_during_profile_setup(): void
    {
        $admin = User::factory()->create([
            'role' => User::ROLE_SDS_ADMIN,
            'must_change_password' => true,
            'email_verified_at' => now(),
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertRedirect(route('profile.edit'));
    }
}
