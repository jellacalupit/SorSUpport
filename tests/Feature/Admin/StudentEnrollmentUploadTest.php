<?php

namespace Tests\Feature\Admin;

use App\Models\Complaint;
use App\Models\ComplaintCategory;
use App\Models\Student;
use App\Models\Ticket;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class StudentEnrollmentUploadTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $college = Unit::create(['name' => 'CICT', 'type' => Unit::TYPE_COLLEGE]);
        $college->programs()->create(['name' => 'BSIT', 'year_level' => 4, 'block' => 5]);
        $this->actingAs(User::factory()->create([
            'role' => User::ROLE_SDS_ADMIN,
            'is_active' => true,
            'must_change_password' => false,
        ]));
    }

    protected function student(string $id, array $attributes = []): User
    {
        $user = User::factory()->create(array_merge([
            'role' => User::ROLE_STUDENT,
            'username' => $id,
            'email' => $id . '@student.test',
            'is_active' => true,
            'email_verified_at' => now(),
            'must_change_password' => false,
        ], $attributes));
        Student::create([
            'user_id' => $user->id,
            'student_id' => $id,
            'college' => 'CICT',
            'program' => 'BSIT',
            'year_level' => '1',
            'block' => 'A',
        ]);

        return $user;
    }

    protected function row(string $id, ?string $email = null): array
    {
        return [$id, 'Updated', 'Student', $email ?? $id . '@student.test', 'CICT', 'BSIT', '3', 'B', 'Inactive'];
    }

    protected function headings(): array
    {
        return ['Student ID', 'First Name', 'Last Name', 'Email', 'College', 'Program', 'Year Level', 'Block', 'Status'];
    }

    protected function upload(array $rows): array
    {
        $contents = implode(',', $this->headings()) . "\n";
        foreach ($rows as $row) {
            $contents .= implode(',', $row) . "\n";
        }

        $this->post(route('admin.accounts.upload.store'), [
            'account_type' => User::ROLE_STUDENT,
            'file' => UploadedFile::fake()->createWithContent('enrolled.csv', $contents),
        ])->assertRedirect(route('admin.accounts.index', ['category_filter' => 'students']))
            ->assertSessionHasNoErrors();

        return session('upload_summary');
    }

    public function test_roster_updates_deactivates_reactivates_and_provisions_students_without_resetting_credentials(): void
    {
        $current = $this->student('20240001');
        $absent = $this->student('20240002');
        $returning = $this->student('20240003', ['is_active' => false]);
        $unverified = $this->student('20240004', [
            'is_active' => false, 'email_verified_at' => null, 'must_change_password' => true,
        ]);
        $staff = User::factory()->create(['role' => User::ROLE_RECIPIENT, 'is_active' => true]);
        $password = $returning->password;
        $verifiedAt = $returning->email_verified_at;

        $category = ComplaintCategory::create(['name' => 'General', 'is_active' => true]);
        $complaint = Complaint::create([
            'reference_number' => Complaint::generateReferenceNumber(),
            'student_id' => $absent->student->id,
            'category_id' => $category->id,
            'subject_title' => 'Existing concern',
            'description' => 'History must survive enrollment synchronization.',
        ]);
        $ticket = Ticket::create(['complaint_id' => $complaint->id, 'status' => Ticket::STATUS_SUBMITTED]);

        $summary = $this->upload([
            $this->row('20240001', 'corrected@student.test'),
            $this->row('20240003'), $this->row('20240004'), $this->row('20240005'),
        ]);

        $this->assertSame(1, $summary['created']);
        $this->assertSame(3, $summary['updated']);
        $this->assertSame(1, $summary['reactivated']);
        $this->assertSame(1, $summary['deactivated']);
        $this->assertTrue($current->fresh()->is_active);
        $this->assertSame('corrected@student.test', $current->fresh()->email);
        $this->assertDatabaseHas('students', ['user_id' => $current->id, 'year_level' => '3', 'block' => 'B']);
        $this->assertFalse($absent->fresh()->is_active);
        $this->assertSame($absent->id, $complaint->fresh()->student->user_id);
        $this->assertDatabaseHas('tickets', ['id' => $ticket->id, 'complaint_id' => $complaint->id]);
        $this->assertTrue($returning->fresh()->is_active);
        $this->assertSame($password, $returning->fresh()->password);
        $this->assertTrue($verifiedAt->equalTo($returning->fresh()->email_verified_at));
        $this->assertFalse((bool) $returning->fresh()->must_change_password);
        $this->assertFalse($unverified->fresh()->is_active);
        $this->assertNull($unverified->fresh()->email_verified_at);
        $new = User::where('username', '20240005')->firstOrFail();
        $this->assertFalse($new->is_active);
        $this->assertNull($new->email_verified_at);
        $this->assertTrue((bool) $new->must_change_password);
        $this->assertTrue(Hash::check('20240005', $new->password));
        $this->assertTrue($staff->fresh()->is_active);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'accounts_imported',
            'details' => 'Imported 4 student account(s); created 1, updated 3, reactivated 1, deactivated 1, rejected 0 row(s).',
        ]);

        // Uploading the same roster again does not count already-applied status changes.
        $repeated = $this->upload([
            $this->row('20240001', 'corrected@student.test'),
            $this->row('20240003'), $this->row('20240004'), $this->row('20240005'),
        ]);
        $this->assertSame(0, $repeated['deactivated']);
        $this->assertSame(0, $repeated['reactivated']);
    }

    public function test_invalid_rows_allow_valid_updates_but_prevent_missing_student_deactivation(): void
    {
        $absent = $this->student('20240001');
        $invalid = $this->row('20240003');
        $invalid[4] = 'Unknown College';

        $summary = $this->upload([$this->row('20240002'), $invalid]);

        $this->assertSame(1, $summary['imported']);
        $this->assertSame(1, $summary['failed']);
        $this->assertSame(0, $summary['deactivated']);
        $this->assertTrue($absent->fresh()->is_active);
        $this->assertStringContainsString('Missing students were not deactivated', session('success'));
    }

    public function test_duplicate_rows_prevent_missing_student_deactivation(): void
    {
        $absent = $this->student('20240001');

        $summary = $this->upload([$this->row('20240002'), $this->row('20240002')]);

        $this->assertSame(1, $summary['duplicates']);
        $this->assertSame(0, $summary['deactivated']);
        $this->assertTrue($absent->fresh()->is_active);
    }

    public function test_empty_rosters_do_not_deactivate_students(): void
    {
        $absent = $this->student('20240001');

        $summary = $this->upload([]);

        $this->assertSame(0, $summary['imported']);
        $this->assertSame(0, $summary['deactivated']);
        $this->assertTrue($absent->fresh()->is_active);
    }

    public function test_reused_email_with_a_different_student_id_does_not_reassign_an_existing_account(): void
    {
        $existing = $this->student('20240001');

        $summary = $this->upload([$this->row('20240002', $existing->email)]);

        $this->assertSame(1, $summary['failed']);
        $this->assertSame(0, $summary['created']);
        $this->assertSame(0, $summary['deactivated']);
        $this->assertTrue($existing->fresh()->is_active);
        $this->assertSame('20240001', $existing->fresh()->username);
        $this->assertDatabaseMissing('students', ['student_id' => '20240002']);
    }

    public function test_enrollment_is_reconciled_after_all_excel_worksheets_are_imported(): void
    {
        $first = $this->student('20240001');
        $second = $this->student('20240002');
        $absent = $this->student('20240003');
        $spreadsheet = new Spreadsheet();
        $spreadsheet->getActiveSheet()->fromArray([$this->headings(), $this->row('20240001')]);
        $spreadsheet->createSheet()->fromArray([$this->headings(), $this->row('20240002')]);
        $stream = fopen('php://memory', 'w+');
        (new Xlsx($spreadsheet))->save($stream);
        rewind($stream);
        $contents = stream_get_contents($stream);
        fclose($stream);
        $spreadsheet->disconnectWorksheets();

        $this->post(route('admin.accounts.upload.store'), [
            'account_type' => User::ROLE_STUDENT,
            'file' => UploadedFile::fake()->createWithContent('enrolled.xlsx', $contents),
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame(2, session('upload_summary.imported'));
        $this->assertSame(1, session('upload_summary.deactivated'));
        $this->assertTrue($first->fresh()->is_active);
        $this->assertTrue($second->fresh()->is_active);
        $this->assertFalse($absent->fresh()->is_active);
    }

    public function test_recipient_upload_does_not_reconcile_student_enrollment(): void
    {
        $student = $this->student('20240001');
        $file = UploadedFile::fake()->createWithContent('staff.csv', "Staff ID,Full Name,Email\nR001,Example Recipient,recipient@test.local\n");

        $this->post(route('admin.accounts.upload.store'), [
            'account_type' => User::ROLE_RECIPIENT, 'file' => $file,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame(0, session('upload_summary.deactivated'));
        $this->assertTrue($student->fresh()->is_active);
    }
}
