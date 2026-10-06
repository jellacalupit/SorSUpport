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

    public function test_starter_categories_are_added_once_and_keep_existing_ones(): void
    {
        $admin = $this->admin();
        $existing = $this->category(['name' => 'Academic Concerns', 'description' => 'My own wording.']);

        $this->actingAs($admin)
            ->post(route('admin.categories.starters'))
            ->assertRedirect(route('admin.settings'));

        $starterCount = count(ComplaintCategory::starterCategories());
        $this->assertSame($starterCount, ComplaintCategory::count());
        $this->assertSame('My own wording.', $existing->fresh()->description);
        $this->assertDatabaseHas('complaint_categories', [
            'name' => 'Gender-Based Sexual Harassment',
            'is_sensitive' => true,
            'allows_hidden_identity' => true,
            'is_active' => true,
        ]);

        $this->actingAs($admin)->post(route('admin.categories.starters'));

        $this->assertSame($starterCount, ComplaintCategory::count());
    }

    public function test_settings_page_offers_the_starter_categories_only_when_there_are_none(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->get(route('admin.settings'))
            ->assertOk()
            ->assertSee('Add starter categories');

        $this->category(['name' => 'Harassment', 'is_sensitive' => true]);
        $this->category(['name' => 'Records', 'allows_hidden_identity' => false]);

        $this->actingAs($admin)->get(route('admin.settings'))
            ->assertOk()
            ->assertDontSee('Add starter categories')
            ->assertSee('Sensitive')
            ->assertSee('Name required');
    }

    public function test_only_the_admin_can_add_starter_categories(): void
    {
        $this->actingAs($this->student())
            ->post(route('admin.categories.starters'))
            ->assertForbidden();

        $this->assertSame(0, ComplaintCategory::count());
    }

    public function test_a_student_cannot_hide_their_identity_where_the_category_requires_a_name(): void
    {
        $student = $this->student();
        $category = $this->category(['name' => 'Academic Concerns', 'allows_hidden_identity' => false]);
        $ticket = [
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
                'category_id' => $inactive->id,
                'subject_title' => 'Old category',
                'description' => 'Trying an inactive category.',
            ])
            ->assertSessionHasErrors('category_id');
    }
}
