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
            'name' => 'College of Computing',
            'positions' => ['Dean', 'Program Chair'],
        ]);

        $response->assertRedirect(route('admin.settings', ['settings_tab' => 'department']));
        $department = Department::where('name', 'College of Computing')->firstOrFail();
        $this->assertSame(['Dean', 'Program Chair'], DepartmentPosition::where('department_id', $department->id)->orderBy('name')->pluck('name')->all());
    }
}
