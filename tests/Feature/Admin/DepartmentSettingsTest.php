<?php

namespace Tests\Feature\Admin;

use App\Models\Department;
use App\Models\DepartmentPosition;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DepartmentSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_department_with_confirmed_positions(): void
    {
        $admin = User::factory()->create([
            'role' => User::ROLE_SDS_ADMIN,
            'is_active' => true,
            'email_verified_at' => now(),
            'must_change_password' => false,
        ]);

        $response = $this->actingAs($admin)->post(route('admin.departments.store'), [
            'type' => 'recipient',
            'name' => 'College of Computing',
            'positions' => ['Dean', 'Program Chair'],
        ]);

        $response->assertRedirect(route('admin.settings', ['settings_tab' => 'department']));
        $department = Department::where('name', 'College of Computing')->firstOrFail();
        $this->assertSame(['Dean', 'Program Chair'], DepartmentPosition::where('department_id', $department->id)->orderBy('name')->pluck('name')->all());
    }

    public function test_admin_can_create_student_department_with_course_configuration(): void
    {
        $admin = User::factory()->create([
            'role' => User::ROLE_SDS_ADMIN,
            'is_active' => true,
            'email_verified_at' => now(),
            'must_change_password' => false,
        ]);

        $response = $this->actingAs($admin)->post(route('admin.departments.store'), [
            'type' => 'student',
            'name' => 'College of Computing',
            'description' => 'Academic programs for computing disciplines.',
            'courses' => [
                ['course' => 'BSIT', 'year_level' => 4, 'block' => '5', 'description' => 'Information technology program for systems and software development.'],
            ],
        ]);

        $response->assertRedirect(route('admin.settings', ['settings_tab' => 'department']));
        $department = Department::where('name', 'College of Computing')->firstOrFail();
        $this->assertSame('Academic programs for computing disciplines.', $department->description);
        $this->assertDatabaseHas('department_courses', [
            'department_id' => $department->id,
            'course' => 'BSIT',
            'year_level' => 4,
            'block' => '5',
            'description' => 'Information technology program for systems and software development.',
        ]);
    }

    public function test_admin_can_update_department_and_course_descriptions(): void
    {
        $admin = User::factory()->create([
            'role' => User::ROLE_SDS_ADMIN,
            'is_active' => true,
            'email_verified_at' => now(),
            'must_change_password' => false,
        ]);
        $department = Department::create(['name' => 'Old Name', 'type' => 'student', 'description' => 'Old description']);
        $department->courses()->create(['course' => 'BSIT', 'year_level' => 2, 'block' => '3', 'description' => 'Old course description']);

        $response = $this->actingAs($admin)->put(route('admin.departments.update', $department), [
            'name' => 'New Name',
            'type' => 'student',
            'description' => 'Updated department description.',
            'courses' => [['course' => 'BSCS', 'year_level' => 4, 'block' => null, 'description' => 'Updated course description.']],
        ]);

        $response->assertRedirect(route('admin.settings', ['settings_tab' => 'department']));
        $department->refresh();
        $this->assertSame('Updated department description.', $department->description);
        $this->assertDatabaseMissing('department_courses', ['department_id' => $department->id, 'course' => 'BSIT']);
        $this->assertDatabaseHas('department_courses', ['department_id' => $department->id, 'course' => 'BSCS', 'year_level' => 4, 'block' => null, 'description' => 'Updated course description.']);
    }

    public function test_student_and_recipient_departments_can_share_a_name(): void
    {
        $admin = User::factory()->create([
            'role' => User::ROLE_SDS_ADMIN,
            'is_active' => true,
            'email_verified_at' => now(),
            'must_change_password' => false,
        ]);

        $this->actingAs($admin)->post(route('admin.departments.store'), [
            'type' => 'student',
            'name' => 'CICT',
            'courses' => [['course' => 'BSIT', 'year_level' => 1]],
        ])->assertRedirect();

        $this->actingAs($admin)->post(route('admin.departments.store'), [
            'type' => 'recipient',
            'name' => 'CICT',
        ])->assertRedirect();

        $this->assertSame(2, Department::where('name', 'CICT')->count());
    }

    public function test_admin_can_edit_student_department_configuration(): void
    {
        $admin = User::factory()->create([
            'role' => User::ROLE_SDS_ADMIN,
            'is_active' => true,
            'email_verified_at' => now(),
            'must_change_password' => false,
        ]);
        $department = Department::create(['name' => 'Old Name', 'type' => 'student']);
        $department->courses()->create(['course' => 'BSIT', 'year_level' => 2, 'block' => '3']);

        $response = $this->actingAs($admin)->put(route('admin.departments.update', $department), [
            'name' => 'New Name',
            'type' => 'student',
            'courses' => [['course' => 'BSCS', 'year_level' => 4, 'block' => null]],
        ]);

        $response->assertRedirect(route('admin.settings', ['settings_tab' => 'department']));
        $this->assertDatabaseHas('departments', ['id' => $department->id, 'name' => 'New Name']);
        $this->assertDatabaseMissing('department_courses', ['department_id' => $department->id, 'course' => 'BSIT']);
        $this->assertDatabaseHas('department_courses', ['department_id' => $department->id, 'course' => 'BSCS', 'year_level' => 4, 'block' => null]);
    }

    public function test_admin_can_delete_a_department(): void
    {
        $admin = User::factory()->create([
            'role' => User::ROLE_SDS_ADMIN,
            'is_active' => true,
            'email_verified_at' => now(),
            'must_change_password' => false,
        ]);
        $department = Department::create(['name' => 'Temporary Department', 'type' => 'recipient']);

        $response = $this->actingAs($admin)->delete(route('admin.departments.destroy', $department));

        $response->assertRedirect(route('admin.settings', ['settings_tab' => 'department']));
        $this->assertDatabaseMissing('departments', ['id' => $department->id]);
    }
}
