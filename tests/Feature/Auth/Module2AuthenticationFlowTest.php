<?php

namespace Tests\Feature\Auth;

use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class Module2AuthenticationFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_user_is_redirected_to_password_change_on_first_login(): void
    {
        /** @var User $user */
        $user = User::factory()->unverified()->create([
            'username' => 'student123',
            'password' => bcrypt('password'),
            'must_change_password' => true,
        ]);

        $this->get('/login');

        $response = $this->post('/login', [
            'username' => 'student123',
            'password' => 'password',
        ]);

        $response->assertRedirect(route('password.force'));
        $this->assertAuthenticatedAs($user);
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
}
