<?php

namespace Tests\Feature\Auth;

use App\Models\Recipient;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class DefaultPasswordTest extends TestCase
{
    use RefreshDatabase;

    /**
     * An account the admin activated manually: verified and active, still on its default password.
     */
    protected function activatedStudent(array $attributes = []): User
    {
        $user = User::factory()->create($attributes + [
            'role' => User::ROLE_STUDENT,
            'name' => 'Maria Santos',
            'first_name' => 'Maria',
            'last_name' => 'Santos',
            'username' => '20240001',
            'password' => Hash::make('20240001'),
            'must_change_password' => true,
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        Student::create([
            'user_id' => $user->id,
            'student_id' => '20240001',
            'college' => 'CICT',
            'program' => 'BSIT',
            'year_level' => '1',
            'block' => '1',
        ]);

        return $user;
    }

    public function test_logging_in_with_the_id_as_password_leads_to_the_create_password_page(): void
    {
        $this->activatedStudent();

        $this->post(route('login'), ['username' => '20240001', 'password' => '20240001'])
            ->assertRedirect(route('password.force'));
    }

    public function test_a_student_on_the_default_password_cannot_open_any_page_first(): void
    {
        $student = $this->activatedStudent();

        $this->actingAs($student)->get(route('student.dashboard'))->assertRedirect(route('password.force'));
        $this->actingAs($student)->get(route('student.complaints.create'))->assertRedirect(route('password.force'));
    }

    public function test_a_recipient_on_the_default_password_cannot_open_any_page_first(): void
    {
        $user = User::factory()->create([
            'role' => User::ROLE_RECIPIENT,
            'username' => 'STAFF-01',
            'password' => Hash::make('STAFF-01'),
            'must_change_password' => true,
            'is_active' => true,
            'email_verified_at' => now(),
        ]);
        Recipient::create(['user_id' => $user->id, 'staff_id' => 'STAFF-01', 'unit' => 'Registrar', 'designation' => 'Registrar']);

        $this->actingAs($user)->get(route('recipient.dashboard'))->assertRedirect(route('password.force'));
    }

    public function test_the_id_as_password_is_caught_even_when_the_account_was_not_flagged(): void
    {
        $student = $this->activatedStudent(['must_change_password' => false]);

        $this->post(route('login'), ['username' => '20240001', 'password' => '20240001'])
            ->assertRedirect(route('password.force'));

        $this->assertTrue((bool) $student->fresh()->must_change_password);
    }

    public function test_the_new_password_cannot_be_the_id_and_a_real_one_opens_the_homepage(): void
    {
        $student = $this->activatedStudent();

        $this->actingAs($student)
            ->post(route('password.force.update'), ['password' => '20240001', 'password_confirmation' => '20240001'])
            ->assertSessionHasErrors('password');

        $this->assertTrue((bool) $student->fresh()->must_change_password);

        $this->actingAs($student)
            ->post(route('password.force.update'), ['password' => 'NewPass@2026', 'password_confirmation' => 'NewPass@2026'])
            ->assertRedirect(route('student.dashboard'));

        $this->assertFalse((bool) $student->fresh()->must_change_password);
        $this->actingAs($student->fresh())->get(route('student.dashboard'))->assertOk();
    }

    public function test_the_header_shows_initials_when_there_is_no_photo(): void
    {
        $student = $this->activatedStudent(['must_change_password' => false]);

        $this->actingAs($student)->get(route('student.dashboard'))
            ->assertOk()
            ->assertSeeInOrder(['Account menu', 'MS']);
    }
}
