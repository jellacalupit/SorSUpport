<?php

namespace Tests\Feature\Admin;

use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UnitSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function admin(): User
    {
        return User::factory()->create([
            'role' => User::ROLE_SDS_ADMIN,
            'is_active' => true,
            'email_verified_at' => now(),
            'must_change_password' => false,
        ]);
    }

    public function test_admin_can_create_an_office_with_designations(): void
    {
        $response = $this->actingAs($this->admin())->post(route('admin.units.store'), [
            'type' => Unit::TYPE_OFFICE,
            'name' => 'Guidance and Counseling Services Unit',
            'designations' => [
                ['name' => 'Guidance Counselor', 'description' => 'Handles counseling referrals.'],
                ['name' => 'Office Head'],
            ],
        ]);

        $response->assertRedirect(route('admin.settings', ['settings_tab' => 'units']));
        $office = Unit::where('name', 'Guidance and Counseling Services Unit')->firstOrFail();
        $this->assertSame(Unit::TYPE_OFFICE, $office->type);
        $this->assertSame(['Guidance Counselor', 'Office Head'], $office->designations->pluck('name')->all());
        $this->assertDatabaseHas('unit_designations', ['unit_id' => $office->id, 'name' => 'Guidance Counselor', 'description' => 'Handles counseling referrals.']);
    }

    public function test_admin_can_create_a_college_with_programs_and_designations(): void
    {
        $response = $this->actingAs($this->admin())->post(route('admin.units.store'), [
            'type' => Unit::TYPE_COLLEGE,
            'name' => 'College of Computing',
            'description' => 'Academic programs for computing disciplines.',
            'programs' => [
                ['name' => 'BSIT', 'year_level' => 4, 'block' => '5', 'description' => 'Information technology program.'],
            ],
            'designations' => [['name' => 'Dean'], ['name' => 'Program Chair']],
        ]);

        $response->assertRedirect(route('admin.settings', ['settings_tab' => 'units']));
        $college = Unit::where('name', 'College of Computing')->firstOrFail();
        $this->assertSame(Unit::TYPE_COLLEGE, $college->type);
        $this->assertSame('Academic programs for computing disciplines.', $college->description);
        $this->assertDatabaseHas('programs', [
            'unit_id' => $college->id,
            'name' => 'BSIT',
            'year_level' => 4,
            'block' => '5',
            'description' => 'Information technology program.',
        ]);
        $this->assertSame(['Dean', 'Program Chair'], $college->designations->pluck('name')->all());
    }

    public function test_a_college_needs_at_least_one_program(): void
    {
        $this->actingAs($this->admin())->post(route('admin.units.store'), [
            'type' => Unit::TYPE_COLLEGE,
            'name' => 'College Without Programs',
        ])->assertSessionHasErrors('programs');

        $this->assertDatabaseMissing('units', ['name' => 'College Without Programs']);
    }

    public function test_a_college_and_an_office_cannot_share_a_name(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('admin.units.store'), [
            'type' => Unit::TYPE_COLLEGE,
            'name' => 'CICT',
            'programs' => [['name' => 'BSIT', 'year_level' => 1]],
        ])->assertSessionHasNoErrors();

        $this->actingAs($admin)->post(route('admin.units.store'), [
            'type' => Unit::TYPE_OFFICE,
            'name' => 'CICT',
        ])->assertSessionHasErrors('name');

        $this->actingAs($admin)->post(route('admin.units.store'), [
            'type' => Unit::TYPE_COLLEGE,
            'name' => 'CICT',
            'programs' => [['name' => 'BSCS', 'year_level' => 1]],
        ])->assertSessionHasErrors('name');

        $this->assertSame(1, Unit::where('name', 'CICT')->count());
    }

    public function test_admin_can_update_a_college(): void
    {
        $college = Unit::create(['name' => 'Old Name', 'type' => Unit::TYPE_COLLEGE, 'description' => 'Old description']);
        $college->programs()->create(['name' => 'BSIT', 'year_level' => 2, 'block' => '3', 'description' => 'Old program description']);
        $college->designations()->create(['name' => 'Old Designation']);

        $response = $this->actingAs($this->admin())->put(route('admin.units.update', $college), [
            'name' => 'New Name',
            'description' => 'Updated college description.',
            'programs' => [['name' => 'BSCS', 'year_level' => 4, 'block' => null, 'description' => 'Updated program description.']],
            'designations' => [['name' => 'Dean', 'description' => 'Heads the college.']],
        ]);

        $response->assertRedirect(route('admin.settings', ['settings_tab' => 'units']));
        $this->assertDatabaseHas('units', ['id' => $college->id, 'name' => 'New Name', 'description' => 'Updated college description.', 'type' => Unit::TYPE_COLLEGE]);
        $this->assertDatabaseMissing('programs', ['unit_id' => $college->id, 'name' => 'BSIT']);
        $this->assertDatabaseHas('programs', ['unit_id' => $college->id, 'name' => 'BSCS', 'year_level' => 4, 'block' => null, 'description' => 'Updated program description.']);
        $this->assertDatabaseMissing('unit_designations', ['unit_id' => $college->id, 'name' => 'Old Designation']);
        $this->assertDatabaseHas('unit_designations', ['unit_id' => $college->id, 'name' => 'Dean', 'description' => 'Heads the college.']);
    }

    public function test_admin_can_update_the_designations_of_an_office(): void
    {
        $office = Unit::create(['name' => 'Old Office', 'type' => Unit::TYPE_OFFICE]);
        $office->designations()->create(['name' => 'Old Designation', 'description' => 'Old description']);

        $response = $this->actingAs($this->admin())->put(route('admin.units.update', $office), [
            'name' => 'New Office',
            'description' => 'Updated office.',
            'designations' => [['name' => 'New Designation', 'description' => 'Updated designation description.']],
        ]);

        $response->assertRedirect(route('admin.settings', ['settings_tab' => 'units']));
        $this->assertDatabaseHas('units', ['id' => $office->id, 'name' => 'New Office']);
        $this->assertDatabaseMissing('unit_designations', ['unit_id' => $office->id, 'name' => 'Old Designation']);
        $this->assertDatabaseHas('unit_designations', ['unit_id' => $office->id, 'name' => 'New Designation', 'description' => 'Updated designation description.']);
    }

    public function test_admin_can_delete_a_college_or_an_office(): void
    {
        $college = Unit::create(['name' => 'Temporary College', 'type' => Unit::TYPE_COLLEGE]);
        $college->programs()->create(['name' => 'BSIT', 'year_level' => 4]);
        $college->designations()->create(['name' => 'Dean']);

        $response = $this->actingAs($this->admin())->delete(route('admin.units.destroy', $college));

        $response->assertRedirect(route('admin.settings', ['settings_tab' => 'units']));
        $this->assertDatabaseMissing('units', ['id' => $college->id]);
        $this->assertDatabaseMissing('programs', ['unit_id' => $college->id]);
        $this->assertDatabaseMissing('unit_designations', ['unit_id' => $college->id]);
    }

    public function test_settings_page_lists_colleges_and_offices(): void
    {
        $college = Unit::create(['name' => 'College of Computing', 'type' => Unit::TYPE_COLLEGE]);
        $college->programs()->create(['name' => 'BS Information Technology', 'year_level' => 4, 'block' => 5]);
        $college->designations()->create(['name' => 'Program Chair']);
        Unit::create(['name' => 'Registrar Office', 'type' => Unit::TYPE_OFFICE])
            ->designations()
            ->create(['name' => 'Registrar']);

        $this->actingAs($this->admin())
            ->get(route('admin.settings', ['settings_tab' => 'units']))
            ->assertOk()
            ->assertSee('Colleges and Offices')
            ->assertSee('College of Computing')
            ->assertSee('BS Information Technology')
            ->assertSee('Program Chair')
            ->assertSee('Registrar Office')
            ->assertSee('Registrar')
            ->assertDontSee('Student Departments')
            ->assertDontSee('Recipient Departments')
            ->assertDontSee('Add department')
            ->assertDontSee('Department Name');
    }

    public function test_only_the_admin_can_manage_colleges_and_offices(): void
    {
        $student = User::factory()->create(['role' => User::ROLE_STUDENT]);

        $this->actingAs($student)->post(route('admin.units.store'), [
            'type' => Unit::TYPE_OFFICE,
            'name' => 'Sneaky Office',
        ])->assertForbidden();

        $this->assertDatabaseMissing('units', ['name' => 'Sneaky Office']);
    }
}
