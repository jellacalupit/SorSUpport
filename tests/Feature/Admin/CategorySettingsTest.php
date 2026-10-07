<?php

namespace Tests\Feature\Admin;

use App\Models\Complaint;
use App\Models\ComplaintCategory;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategorySettingsTest extends TestCase
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

    protected function student(): User
    {
        $user = User::factory()->create(['role' => User::ROLE_STUDENT]);

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

    protected function category(array $attributes = []): ComplaintCategory
    {
        return ComplaintCategory::create($attributes + [
            'name' => 'General',
            'resolution_deadline_days' => 15,
            'is_active' => true,
        ]);
    }

    public function test_a_new_category_allows_hidden_identity_and_is_not_sensitive_by_default(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.categories.store'), ['name' => 'Food and Housing'])
            ->assertSessionHasNoErrors();

        $category = ComplaintCategory::where('name', 'Food and Housing')->firstOrFail();
        $this->assertFalse($category->is_sensitive);
        $this->assertTrue($category->allows_hidden_identity);
    }

    public function test_admin_can_require_a_name_for_a_category(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.categories.store'), [
                'name' => 'Academic Concerns',
                'allows_hidden_identity' => '0',
                'is_sensitive' => '0',
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('complaint_categories', [
            'name' => 'Academic Concerns',
            'allows_hidden_identity' => false,
            'is_sensitive' => false,
        ]);
    }

    public function test_a_sensitive_category_always_allows_hidden_identity(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.categories.store'), [
                'name' => 'Gender-Based Sexual Harassment',
                'is_sensitive' => '1',
                'allows_hidden_identity' => '0',
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('complaint_categories', [
            'name' => 'Gender-Based Sexual Harassment',
            'is_sensitive' => true,
            'allows_hidden_identity' => true,
        ]);
    }

    public function test_admin_can_change_the_handling_options_of_a_category(): void
    {
        $category = $this->category(['name' => 'Bullying']);

        $this->actingAs($this->admin())
            ->put(route('admin.categories.update', $category), [
                'name' => 'Bullying and Discrimination',
                'is_sensitive' => '1',
                'allows_hidden_identity' => '1',
            ])
            ->assertSessionHasNoErrors();

        $category->refresh();
        $this->assertSame('Bullying and Discrimination', $category->name);
        $this->assertTrue($category->is_sensitive);
        $this->assertTrue($category->allows_hidden_identity);
    }

    public function test_updating_a_category_without_the_options_keeps_them(): void
    {
        $category = $this->category(['name' => 'Harassment', 'is_sensitive' => true, 'allows_hidden_identity' => true]);
        $nameRequired = $this->category(['name' => 'Records', 'allows_hidden_identity' => false]);

        $admin = $this->admin();
        $this->actingAs($admin)->put(route('admin.categories.update', $category), ['name' => 'Harassment Renamed']);
        $this->actingAs($admin)->put(route('admin.categories.update', $nameRequired), ['name' => 'Records Renamed']);

        $this->assertTrue($category->fresh()->is_sensitive);
        $this->assertFalse($nameRequired->fresh()->allows_hidden_identity);
    }

    public function test_the_add_category_form_starts_with_no_option_selected(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->get(route('admin.settings'))
            ->assertOk()
            ->assertDontSee('Add starter categories')
            ->assertSee('hiddenIdentity: false, sensitive: false', false);

        // What the form sends when the admin ticks nothing.
        $this->actingAs($admin)
            ->post(route('admin.categories.store'), [
                'name' => 'Facilities',
                'allows_hidden_identity' => '0',
                'is_sensitive' => '0',
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('complaint_categories', [
            'name' => 'Facilities',
            'allows_hidden_identity' => false,
            'is_sensitive' => false,
        ]);
    }

    public function test_category_cards_show_their_handling_options(): void
    {
        $this->category(['name' => 'Harassment', 'is_sensitive' => true]);
        $this->category(['name' => 'Records', 'allows_hidden_identity' => false]);

        $this->actingAs($this->admin())->get(route('admin.settings'))
            ->assertOk()
            ->assertSee('Sensitive')
            ->assertSee('Name required');
    }

    public function test_a_student_cannot_hide_their_identity_where_the_category_requires_a_name(): void
    {
        $student = $this->student();
        $category = $this->category(['name' => 'Academic Concerns', 'allows_hidden_identity' => false]);
        $ticket = [
            'declaration' => '1',
            'category_id' => $category->id,
            'subject_title' => 'Grade concern',
            'description' => 'My grade was not encoded.',
        ];

        $this->actingAs($student)
            ->post(route('student.complaints.store'), $ticket + ['is_anonymous' => '1'])
            ->assertSessionHasErrors('is_anonymous');

        $this->assertSame(0, Complaint::count());

        $this->actingAs($student)
            ->post(route('student.complaints.store'), $ticket)
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('complaints', ['subject_title' => 'Grade concern', 'is_anonymous' => false]);
    }

    public function test_a_student_can_hide_their_identity_where_the_category_allows_it(): void
    {
        $category = $this->category(['name' => 'Harassment', 'is_sensitive' => true]);

        $this->actingAs($this->student())
            ->post(route('student.complaints.store'), [
                'declaration' => '1',
                'category_id' => $category->id,
                'subject_title' => 'Unwanted messages',
                'description' => 'I keep receiving unwanted messages.',
                'is_anonymous' => '1',
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('complaints', ['subject_title' => 'Unwanted messages', 'is_anonymous' => true]);
    }

    public function test_the_ticket_form_lists_only_active_categories_and_rejects_inactive_ones(): void
    {
        $student = $this->student();
        $this->category(['name' => 'Open Category']);
        $inactive = $this->category(['name' => 'Retired Category', 'is_active' => false]);

        $this->actingAs($student)
            ->get(route('student.complaints.create'))
            ->assertOk()
            ->assertSee('Open Category')
            ->assertDontSee('Retired Category');

        $this->actingAs($student)
            ->post(route('student.complaints.store'), [
                'declaration' => '1',
                'category_id' => $inactive->id,
                'subject_title' => 'Old category',
                'description' => 'Trying an inactive category.',
            ])
            ->assertSessionHasErrors('category_id');
    }

    public function test_the_unit_edit_form_carries_its_own_edit_logic(): void
    {
        $admin = User::factory()->create([
            'role' => User::ROLE_SDS_ADMIN,
            'is_active' => true,
            'email_verified_at' => now(),
            'must_change_password' => false,
        ]);
        $office = \App\Models\Unit::create(['type' => \App\Models\Unit::TYPE_OFFICE, 'name' => 'Library']);
        $office->designations()->create(['name' => 'Campus Librarian', 'description' => 'Manages library services.']);

        $this->actingAs($admin)->get(route('admin.settings', ['settings_tab' => 'units']))
            ->assertOk()
            ->assertSee('Manages library services.')
            ->assertSee('positionEditingPosition: null', false)
            ->assertDontSee("addEventListener('alpine:initialized'", false);

        $this->actingAs($admin)->put(route('admin.units.update', $office), [
            'name' => 'Campus Library',
            'designations' => [['name' => 'Campus Librarian', 'description' => 'Runs the library.']],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertDatabaseHas('units', ['id' => $office->id, 'name' => 'Campus Library']);
        $this->assertDatabaseHas('unit_designations', ['unit_id' => $office->id, 'description' => 'Runs the library.']);
    }
}
