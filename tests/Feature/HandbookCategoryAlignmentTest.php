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

    public function test_missing_categories_and_staff_are_skipped(): void
    {
        $library = $this->category('Library Services');

        $this->align();

        $this->assertSame(0, $library->suggestedRecipients()->count());
    }
}
