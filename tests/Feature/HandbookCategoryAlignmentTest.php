<?php

namespace Tests\Feature;

use App\Models\ComplaintCategory;
use App\Models\Recipient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HandbookCategoryAlignmentTest extends TestCase
{
    use RefreshDatabase;

    protected function staff(string $staffId, string $unit, string $designation): Recipient
    {
        $user = User::factory()->create(['role' => User::ROLE_RECIPIENT, 'username' => $staffId]);

        return Recipient::create(['user_id' => $user->id, 'staff_id' => $staffId, 'unit' => $unit, 'designation' => $designation]);
    }

    protected function category(string $name): ComplaintCategory
    {
        return ComplaintCategory::create(['name' => $name, 'is_active' => true]);
    }

    protected function align(): void
    {
        (require database_path('migrations/2026_10_10_100000_align_categories_with_the_handbook.php'))->up();
    }

    public function test_categories_follow_the_handbook(): void
    {
        $sds = $this->staff('230515', 'Student Development and Services', 'SDS Coordinator');
        $gad = $this->staff('230839', 'Gender and Development', 'GAD Focal Person');
        $guidance = $this->staff('220247', 'Guidance and Counseling Services Unit', 'Guidance Counselor');
        $registrar = $this->staff('210764', "Registrar's Office", 'Campus Registrar');
        $faculty = $this->staff('240145', 'CBME', 'Faculty');
        $chair = $this->staff('210537', 'CBME', 'BPA PC');
        $dean = $this->staff('200214', 'CBME', 'Dean');
        $director = $this->staff('200043', 'Office of the Campus Director', 'Campus Director');

        $harassment = $this->category('Gender-Based Sexual Harassment');
        $againstStudent = $this->category('Complaint Against a Student');
        $records = $this->category('Student Records and Documents');
        $overlap = $this->category('Academic Concerns');

        $this->align();

        $this->assertEqualsCanonicalizing([$gad->id, $guidance->id], $harassment->suggestedRecipients()->pluck('recipients.id')->all());

        // Filed with the SDS Coordinator or a teacher, kept confidential, escalated PC > Dean > Director.
        $againstStudent->refresh();
        $this->assertTrue($againstStudent->is_sensitive);
        $this->assertEqualsCanonicalizing([$sds->id, $faculty->id], $againstStudent->suggestedRecipients()->pluck('recipients.id')->all());
        $this->assertSame(
            [[1, $chair->id], [2, $dean->id], [3, $director->id]],
            $againstStudent->escalationHierarchies()->where('path_name', 'CBME')->get()->map(fn ($step) => [(int) $step->level, (int) $step->recipient_id])->all()
        );

        // Guidance issues the Certificate of Good Moral Character.
        $this->assertEqualsCanonicalizing([$registrar->id, $guidance->id], $records->suggestedRecipients()->pluck('recipients.id')->all());

        $this->assertFalse($overlap->fresh()->is_active);
    }

    public function test_the_overlapping_categories_are_deleted_unless_a_ticket_uses_them(): void
    {
        $unused = $this->category('Academic Concerns');
        $kept = $this->category('Registrar and Records');
        $student = \App\Models\Student::create([
            'user_id' => User::factory()->create(['role' => User::ROLE_STUDENT])->id,
            'student_id' => 'S9001',
            'college' => 'CICT',
            'program' => 'BSIT',
            'year_level' => '2nd Year',
            'block' => 'A',
        ]);
        \App\Models\Complaint::create([
            'reference_number' => \App\Models\Complaint::generateReferenceNumber(),
            'student_id' => $student->id,
            'category_id' => $kept->id,
            'subject_title' => 'Transcript request',
            'description' => 'My transcript has not been released.',
            'is_anonymous' => false,
            'status' => \App\Models\Complaint::STATUS_SUBMITTED,
        ]);
        $specific = $this->category('Grades and Examinations');

        (require database_path('migrations/2026_10_10_200000_delete_overlapping_categories.php'))->up();

        $this->assertDatabaseMissing('complaint_categories', ['id' => $unused->id]);
        $this->assertDatabaseHas('complaint_categories', ['id' => $kept->id]);
        $this->assertDatabaseHas('complaint_categories', ['id' => $specific->id]);
    }

    public function test_every_category_gets_an_escalation_path_and_an_audit_entry(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_SDS_ADMIN, 'username' => '230515']);
        $chair = $this->staff('210751', 'CICT', 'BSCS PC');
        $dean = $this->staff('200926', 'CICT', 'Dean');
        $director = $this->staff('200043', 'Office of the Campus Director', 'Campus Director');
        $grades = $this->category('Grades and Examinations');
        $fees = $this->category('Fees and Payments');

        (require database_path('migrations/2026_10_10_400000_add_escalation_paths_to_categories.php'))->up();

        $this->assertSame(
            [[1, $chair->id], [2, $dean->id], [3, $director->id]],
            $grades->escalationHierarchies()->where('path_name', 'CICT')->get()->map(fn ($step) => [(int) $step->level, (int) $step->recipient_id])->all()
        );
        $this->assertSame([$director->id], $fees->escalationHierarchies()->pluck('recipient_id')->map(fn ($id) => (int) $id)->all());
        $this->assertDatabaseHas('audit_logs', [
            'performed_by' => $admin->id,
            'action' => 'escalation_hierarchy_updated',
            'details' => 'Updated the escalation hierarchy of "Fees and Payments".',
        ]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'category_deleted', 'details' => 'Deleted complaint category "Academic Concerns".']);
    }

    public function test_the_gender_categories_merge_and_escalation_staff_are_suggested(): void
    {
        $gad = $this->staff('230839', 'Gender and Development', 'GAD Focal Person');
        $director = $this->staff('200043', 'Office of the Campus Director', 'Campus Director');
        $harassment = $this->category('Gender-Based Sexual Harassment');
        $discrimination = $this->category('Gender and Discrimination Concerns');
        $harassment->suggestedRecipients()->attach($gad->id);
        $discrimination->suggestedRecipients()->attach($gad->id);
        $harassment->escalationHierarchies()->create(['path_number' => 1, 'level' => 1, 'recipient_id' => $director->id]);

        (require database_path('migrations/2026_10_10_500000_merge_gender_categories_and_suggest_escalation_staff.php'))->up();

        $this->assertDatabaseMissing('complaint_categories', ['id' => $discrimination->id]);
        $merged = $harassment->fresh();
        $this->assertSame('Gender-Based Harassment and Discrimination', $merged->name);
        $this->assertTrue($merged->is_sensitive);
        $this->assertEqualsCanonicalizing([$gad->id, $director->id], $merged->suggestedRecipients()->pluck('recipients.id')->all());
        $this->assertDatabaseHas('audit_logs', ['action' => 'category_deleted', 'details' => 'Deleted complaint category "Gender and Discrimination Concerns".']);
    }

    public function test_a_level_holds_several_people_from_the_category_only(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_SDS_ADMIN, 'is_active' => true, 'email_verified_at' => now(), 'must_change_password' => false]);
        $first = $this->staff('210751', 'CICT', 'BSCS PC');
        $second = $this->staff('220384', 'CICT', 'BSIS PC');
        $dean = $this->staff('200926', 'CICT', 'Dean');
        $outsider = $this->staff('220687', 'Library', 'Campus Librarian');
        foreach ([$first, $second, $dean, $outsider] as $staff) {
            // Active is enough; the email does not have to be verified yet.
            $staff->user->update(['is_active' => true, 'email_verified_at' => null]);
        }
        $grades = $this->category('Grades and Examinations');
        $grades->suggestedRecipients()->attach([$first->id, $second->id, $dean->id]);

        $this->actingAs($admin)
            ->put(route('admin.categories.escalation.update', $grades), ['paths' => [['name' => 'CICT', 'levels' => [[$first->id, $second->id], [$dean->id]]]]])
            ->assertSessionHasNoErrors();

        $this->assertSame(
            [[1, $first->id], [1, $second->id], [2, $dean->id]],
            $grades->escalationHierarchies()->orderBy('level')->orderBy('id')->get()->map(fn ($step) => [(int) $step->level, (int) $step->recipient_id])->all()
        );

        // Someone outside the category cannot be added.
        $this->actingAs($admin)
            ->put(route('admin.categories.escalation.update', $grades), ['paths' => [['name' => 'CICT', 'levels' => [[$outsider->id]]]]])
            ->assertSessionHasErrors('paths');
        $this->assertSame(3, $grades->escalationHierarchies()->count());

        $this->actingAs($admin)->get(route('admin.settings', ['settings_tab' => 'escalation']))
            ->assertOk()
            ->assertSee('[levels][${levelIndex}][]', false);
    }

    public function test_missing_categories_and_staff_are_skipped(): void
    {
        $library = $this->category('Library Services');

        $this->align();

        $this->assertSame(0, $library->suggestedRecipients()->count());
    }
}
