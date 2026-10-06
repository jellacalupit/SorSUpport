<?php

namespace Tests\Feature\Admin;

use App\Models\Recipient;
use App\Models\Unit;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class BulkAccountUploadTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Students can only be placed in a college and program configured in System Settings.
        $this->configureCollege('CICT', ['BSIT', 'BSCS']);
        $this->configureCollege('Engineering', ['CS']);
        $this->configureCollege('Technology', ['IT']);
    }

    protected function configureCollege(string $name, array $programs): Unit
    {
        $college = Unit::create(['name' => $name, 'type' => Unit::TYPE_COLLEGE]);

        foreach ($programs as $program) {
            $college->programs()->create(['name' => $program, 'year_level' => 4, 'block' => 5]);
        }

        return $college;
    }

    protected function admin(): User
    {
        return User::factory()->create([
            'role' => User::ROLE_SDS_ADMIN,
            'email_verified_at' => now(),
            'must_change_password' => false,
        ]);
    }

    public function test_bulk_upload_reads_the_college_and_program_columns(): void
    {
        $file = UploadedFile::fake()->createWithContent('students.csv', "Student ID,Last Name,First Name,Email,College,Program,Year,Block\n20245001,Cruz,Ana,ana@student.test,CICT,BSCS,2,1\n");

        $this->actingAs($this->admin())->post(route('admin.accounts.upload.store'), [
            'account_type' => User::ROLE_STUDENT,
            'file' => $file,
        ]);

        $this->assertSame(1, session('upload_summary')['imported']);
        $this->assertDatabaseHas('students', ['student_id' => '20245001', 'college' => 'CICT', 'program' => 'BSCS']);
    }

    public function test_bulk_upload_reports_unknown_colleges_and_programs(): void
    {
        $file = UploadedFile::fake()->createWithContent('students.csv', "Student ID,Last Name,First Name,Email,College,Program,Year,Block\n20246001,Unknown,College,one@student.test,Not A College,BSIT,1,1\n20246002,Unknown,Program,two@student.test,CICT,Not A Program,1,1\n20246003,Wrong,College,three@student.test,Engineering,BSIT,1,1\n");

        $this->actingAs($this->admin())->post(route('admin.accounts.upload.store'), [
            'account_type' => User::ROLE_STUDENT,
            'file' => $file,
        ]);

        $summary = session('upload_summary');
        $this->assertSame(0, $summary['imported']);
        $this->assertSame(3, $summary['failed']);
        $this->assertContains('The college is not configured in System Settings.', $summary['errors'][0]['messages']);
        $this->assertContains('The program is not offered by the selected college.', $summary['errors'][1]['messages']);
        $this->assertContains('The program is not offered by the selected college.', $summary['errors'][2]['messages']);
        $this->assertDatabaseCount('students', 0);
    }

    public function test_bulk_upload_places_recipients_in_a_college_or_an_office_and_rejects_unknown_ones(): void
    {
        Unit::create(['name' => 'Registrar Office', 'type' => Unit::TYPE_OFFICE]);

        $file = UploadedFile::fake()->createWithContent('recipients.csv', "Staff ID,Full Name,Email,College / Office,Designation\nR5001,Dean Reyes,dean@recipient.test,CICT,Dean\nR5002,Rita Registrar,rita@recipient.test,Registrar Office,Office Head\nR5003,Nora Nowhere,nora@recipient.test,Unknown Office,Clerk\n");

        $this->actingAs($this->admin())->post(route('admin.accounts.upload.store'), [
            'account_type' => User::ROLE_RECIPIENT,
            'file' => $file,
        ]);

        $summary = session('upload_summary');
        $this->assertSame(2, $summary['imported']);
        $this->assertContains('The college or office is not configured in System Settings.', $summary['errors'][0]['messages']);
        $this->assertDatabaseHas('recipients', ['staff_id' => 'R5001', 'unit' => 'CICT', 'designation' => 'Dean']);
        $this->assertDatabaseHas('recipients', ['staff_id' => 'R5002', 'unit' => 'Registrar Office', 'designation' => 'Office Head']);
        $this->assertDatabaseMissing('recipients', ['staff_id' => 'R5003']);
    }

    public function test_a_student_account_needs_a_configured_college_and_one_of_its_programs(): void
    {
        $admin = $this->admin();
        $student = [
            'first_name' => 'Sam',
            'last_name' => 'Student',
            'email' => 'sam@student.test',
            'role' => User::ROLE_STUDENT,
            'student_id' => '20247001',
            'year_level' => 1,
        ];

        $this->actingAs($admin)
            ->post(route('admin.accounts.store'), $student + ['college' => 'Not A College', 'program' => 'BSIT'])
            ->assertSessionHasErrors('college');

        // CS is offered by Engineering, not by CICT.
        $this->actingAs($admin)
            ->post(route('admin.accounts.store'), $student + ['college' => 'CICT', 'program' => 'CS'])
            ->assertSessionHasErrors('program');

        $this->assertDatabaseMissing('users', ['email' => 'sam@student.test']);

        $this->actingAs($admin)
            ->post(route('admin.accounts.store'), $student + ['college' => 'CICT', 'program' => 'BSIT'])
            ->assertRedirect(route('admin.accounts.index', ['category_filter' => 'students']));

        $this->assertDatabaseHas('students', ['student_id' => '20247001', 'college' => 'CICT', 'program' => 'BSIT']);
    }

    public function test_a_recipient_account_can_belong_to_a_college_or_an_office(): void
    {
        $admin = $this->admin();
        Unit::create(['name' => 'Guidance Office', 'type' => Unit::TYPE_OFFICE]);

        $recipient = fn (string $staffId, string $unit) => [
            'first_name' => 'Rae',
            'last_name' => 'Recipient',
            'email' => strtolower($staffId) . '@recipient.test',
            'role' => User::ROLE_RECIPIENT,
            'staff_id' => $staffId,
            'unit' => $unit,
            'designation' => 'Head',
        ];

        $this->actingAs($admin)->post(route('admin.accounts.store'), $recipient('R6001', 'CICT'))->assertSessionHasNoErrors();
        $this->actingAs($admin)->post(route('admin.accounts.store'), $recipient('R6002', 'Guidance Office'))->assertSessionHasNoErrors();
        $this->actingAs($admin)->post(route('admin.accounts.store'), $recipient('R6003', 'Unknown Office'))->assertSessionHasErrors('unit');

        $this->assertDatabaseHas('recipients', ['staff_id' => 'R6001', 'unit' => 'CICT']);
        $this->assertDatabaseHas('recipients', ['staff_id' => 'R6002', 'unit' => 'Guidance Office']);
        $this->assertDatabaseMissing('recipients', ['staff_id' => 'R6003']);
    }

    public function test_accounts_can_be_filtered_by_college_and_program(): void
    {
        $make = function (string $id, string $college, string $program): void {
            $user = User::factory()->create(['role' => User::ROLE_STUDENT, 'username' => $id, 'name' => 'Student ' . $id]);
            Student::create(['user_id' => $user->id, 'student_id' => $id, 'college' => $college, 'program' => $program, 'year_level' => '1', 'block' => '1']);
        };
        $make('20248001', 'CICT', 'BSIT');
        $make('20248002', 'CICT', 'BSCS');
        $make('20248003', 'Engineering', 'CS');

        $this->actingAs($this->admin())
            ->get(route('admin.accounts.index', ['unit_filter' => 'CICT', 'program_filter' => 'BSCS']))
            ->assertOk()
            ->assertSee('20248002')
            ->assertDontSee('20248001')
            ->assertDontSee('20248003');
    }

    public function test_bulk_upload_creates_updates_and_deactivates_accounts(): void
    {
        /** @var User $admin */
        $admin = User::factory()->create([
            'username' => 'admin1',
            'role' => User::ROLE_SDS_ADMIN,
            'email_verified_at' => now(),
            'must_change_password' => false,
        ]);

        /** @var User $existingStudent */
        $existingStudent = User::factory()->create([
            'username' => '20241001',
            'email' => 'existing@student.test',
            'role' => User::ROLE_STUDENT,
            'email_verified_at' => now(),
            'must_change_password' => false,
        ]);
        Student::create([
            'user_id' => $existingStudent->id,
            'student_id' => '20241001',
            'college' => 'Technology',
            'program' => 'IT',
            'year_level' => '2nd Year',
            'block' => 'A',
        ]);

        $file = UploadedFile::fake()->createWithContent('accounts.csv', "email,name,student_id,department,course,year_level,block\nnew@student.test,New Student,20242001,Engineering,CS,1st Year,B\nexisting@student.test,Updated Student,20241001,Technology,IT,3rd Year,B\n");

        $response = $this->actingAs($admin)
            ->post(route('admin.accounts.upload.store'), [
                'file' => $file,
            ]);

        $response->assertRedirect(route('admin.accounts.index'));
        $this->assertDatabaseHas('users', ['email' => 'new@student.test', 'role' => User::ROLE_STUDENT, 'is_active' => false]);
        $this->assertDatabaseHas('students', ['student_id' => '20242001']);
        $this->assertDatabaseHas('users', ['email' => 'existing@student.test', 'role' => User::ROLE_STUDENT, 'is_active' => true]);
        $this->assertDatabaseHas('students', ['student_id' => '20241001', 'year_level' => '3rd Year']);
    }

    public function test_bulk_upload_imports_recipients_using_explicit_account_type(): void
    {
        $admin = User::factory()->create([
            'username' => 'admin1',
            'role' => User::ROLE_SDS_ADMIN,
            'email_verified_at' => now(),
            'must_change_password' => false,
        ]);

        $file = UploadedFile::fake()->createWithContent('recipients.csv', "Staff ID,Last Name,First Name,Middle Name,Email,Department,Position,Status\nR2001,Receiver,Rey,,rey@recipient.test,CICT,Coordinator,Active\n");

        $response = $this->actingAs($admin)->post(route('admin.accounts.upload.store'), [
            'account_type' => User::ROLE_RECIPIENT,
            'file' => $file,
        ]);

        $response->assertRedirect(route('admin.accounts.index', ['category_filter' => 'recipients']));
        $this->assertDatabaseHas('users', ['email' => 'rey@recipient.test', 'role' => User::ROLE_RECIPIENT, 'must_change_password' => true]);
        $this->assertDatabaseHas('recipients', ['staff_id' => 'R2001', 'unit' => 'CICT', 'designation' => 'Coordinator']);
    }

    public function test_bulk_upload_page_accepts_selected_account_type(): void
    {
        $admin = User::factory()->create([
            'username' => 'admin1',
            'role' => User::ROLE_SDS_ADMIN,
            'email_verified_at' => now(),
            'must_change_password' => false,
        ]);

        $file = UploadedFile::fake()->createWithContent('recipients.csv', "Staff ID,Full Name,Email,Department,Position,Status\nR3001,Test Recipient,test.recipient@test.local,CICT,Coordinator,Active\n");

        $response = $this->actingAs($admin)->post(route('admin.accounts.upload.store'), [
            'account_type' => User::ROLE_RECIPIENT,
            'file' => $file,
        ]);

        $response->assertRedirect(route('admin.accounts.index', ['category_filter' => 'recipients']));
        $this->assertDatabaseHas('recipients', ['staff_id' => 'R3001']);
    }

    public function test_bulk_upload_accepts_recipient_staff_id_full_name_and_email_only(): void
    {
        $admin = User::factory()->create([
            'username' => 'admin1',
            'role' => User::ROLE_SDS_ADMIN,
            'email_verified_at' => now(),
            'must_change_password' => false,
        ]);

        $file = UploadedFile::fake()->createWithContent('recipients.csv', "Staff ID,Full Name,Email\nR2002,Jamie Recipient,jamie@recipient.test\n");

        $response = $this->actingAs($admin)->post(route('admin.accounts.upload.store'), [
            'account_type' => User::ROLE_RECIPIENT,
            'file' => $file,
        ]);

        $response->assertRedirect(route('admin.accounts.index', ['category_filter' => 'recipients']));
        $this->assertDatabaseHas('users', [
            'email' => 'jamie@recipient.test',
            'name' => 'Jamie Recipient',
            'role' => User::ROLE_RECIPIENT,
        ]);
        $this->assertDatabaseHas('recipients', [
            'staff_id' => 'R2002',
            'unit' => '',
            'designation' => '',
        ]);
    }

    public function test_bulk_upload_reports_invalid_and_duplicate_rows_without_creating_them(): void
    {
        $admin = User::factory()->create([
            'username' => 'admin1',
            'role' => User::ROLE_SDS_ADMIN,
            'email_verified_at' => now(),
            'must_change_password' => false,
        ]);

        $file = UploadedFile::fake()->createWithContent('students.csv', "Student ID,Last Name,First Name,Middle Name,Email,Department,Course,Year,Block,Status\n20243001,Valid,Vera,,vera@student.test,CICT,BSIT,1,,Active\n20243001,Duplicate,Dee,,dee@student.test,CICT,BSIT,1,1,Active\n20243002,Missing,, ,bad-email,CICT,BSIT,5,1,Active\n");

        $response = $this->actingAs($admin)->post(route('admin.accounts.upload.store'), [
            'account_type' => User::ROLE_STUDENT,
            'file' => $file,
        ]);

        $response->assertRedirect(route('admin.accounts.index', ['category_filter' => 'students']));
        $summary = session('upload_summary');
        $this->assertSame(3, $summary['total']);
        $this->assertSame(1, $summary['imported']);
        $this->assertSame(2, $summary['failed']);
        $this->assertSame(1, $summary['duplicates']);
        $this->assertDatabaseHas('students', ['student_id' => '20243001']);
        $this->assertDatabaseMissing('students', ['student_id' => '20243002']);
    }

    public function test_bulk_upload_rejects_a_recipient_request_with_student_only_data(): void
    {
        $admin = User::factory()->create([
            'username' => 'admin1',
            'role' => User::ROLE_SDS_ADMIN,
            'email_verified_at' => now(),
            'must_change_password' => false,
        ]);

        $file = UploadedFile::fake()->createWithContent('students.csv', "Student ID,Last Name,First Name,Email,Department,Course,Year,Block,Status\n20244001,Wrong,Type,wrong@test.local,CICT,BSIT,1,1,Active\n");

        $this->actingAs($admin)->post(route('admin.accounts.upload.store'), [
            'account_type' => User::ROLE_RECIPIENT,
            'file' => $file,
        ])->assertRedirect(route('admin.accounts.index', ['category_filter' => 'recipients']));

        $this->assertDatabaseMissing('users', ['email' => 'wrong@test.local']);
    }

    public function test_bulk_upload_accepts_numeric_student_ids_and_ignores_blank_rows(): void
    {
        $admin = User::factory()->create([
            'username' => 'admin1',
            'role' => User::ROLE_SDS_ADMIN,
            'email_verified_at' => now(),
            'must_change_password' => false,
        ]);

        $file = UploadedFile::fake()->createWithContent('students.csv', "Student ID,Last Name,First Name,Middle Name,Email,Department,Course,Year,Block,Status\n23170100,Reyes,Maria,,maria@example.test,CICT,BSIT,1,,Active\n24180988,Santos,Pedro,,pedro@example.test,CICT,BSIT,2,,Active\n,,,,,,,,,\n");

        $response = $this->actingAs($admin)->post(route('admin.accounts.upload.store'), [
            'account_type' => User::ROLE_STUDENT,
            'file' => $file,
        ]);

        $response->assertRedirect(route('admin.accounts.index', ['category_filter' => 'students']));
        $summary = session('upload_summary');
        $this->assertSame(2, $summary['total']);
        $this->assertSame(2, $summary['imported']);
        $this->assertSame(0, $summary['failed']);
        $this->assertDatabaseHas('students', ['student_id' => '23170100']);
        $this->assertDatabaseHas('students', ['student_id' => '24180988']);
    }

    public function test_admin_manual_account_creation_uses_student_id_as_default_username_without_verification_email(): void
    {
        Notification::fake();

        /** @var User $admin */
        $admin = User::factory()->create([
            'username' => 'admin1',
            'role' => User::ROLE_SDS_ADMIN,
            'email_verified_at' => now(),
            'must_change_password' => false,
        ]);

        $response = $this->actingAs($admin)
            ->post(route('admin.accounts.store'), [
                'first_name' => 'New',
                'middle_name' => '',
                'last_name' => 'Student',
                'email' => 'new.student@test.local',
                'role' => User::ROLE_STUDENT,
                'student_id' => '20245001',
                'college' => 'CICT',
                'program' => 'BSIT',
                'year_level' => 2,
                'block' => 1,
            ]);

        $response->assertRedirect(route('admin.accounts.index', ['category_filter' => 'students']));

        $user = User::where('email', 'new.student@test.local')->first();
        $this->assertNotNull($user);
        $this->assertSame('20245001', $user->username);
        $this->assertTrue(Hash::check('20245001', $user->password));
        $this->assertNull($user->email_verified_at);
        Notification::assertNothingSent();
    }

    public function test_admin_manual_recipient_creation_stays_on_recipients_tab(): void
    {
        /** @var User $admin */
        $admin = User::factory()->create([
            'username' => 'admin1',
            'role' => User::ROLE_SDS_ADMIN,
            'email_verified_at' => now(),
            'must_change_password' => false,
        ]);

        $response = $this->actingAs($admin)
            ->post(route('admin.accounts.store'), [
                'first_name' => 'Lian',
                'middle_name' => '',
                'last_name' => 'Fulgosino',
                'email' => 'lian.fulgosino@test.local',
                'role' => User::ROLE_RECIPIENT,
                'staff_id' => 'R9001',
                'unit' => 'CICT',
                'designation' => 'Coordinator',
            ]);

        $response->assertRedirect(route('admin.accounts.index', ['category_filter' => 'recipients']));
        $this->assertDatabaseHas('users', ['email' => 'lian.fulgosino@test.local', 'role' => User::ROLE_RECIPIENT]);
    }

    public function test_admin_recipient_profile_updates_persist_the_new_values(): void
    {
        /** @var User $admin */
        $admin = User::factory()->create([
            'username' => 'admin1',
            'role' => User::ROLE_SDS_ADMIN,
            'email_verified_at' => now(),
            'must_change_password' => false,
        ]);

        Unit::create(['name' => 'CBME', 'type' => Unit::TYPE_OFFICE]);

        /** @var User $recipient */
        $recipient = User::factory()->create([
            'name' => 'Old Recipient',
            'email' => 'old.recipient@test.local',
            'role' => User::ROLE_RECIPIENT,
            'email_verified_at' => now(),
            'must_change_password' => false,
        ]);

        Recipient::create([
            'user_id' => $recipient->id,
            'staff_id' => 'R1001',
            'unit' => 'CICT',
            'designation' => 'Old Designation',
        ]);

        $response = $this->actingAs($admin)
            ->put(route('admin.accounts.update', $recipient), [
                'first_name' => 'Updated',
                'middle_name' => 'M.',
                'last_name' => 'Recipient',
                'email' => 'updated.recipient@test.local',
                'staff_id' => 'R2002',
                'unit' => 'CBME',
                'designation' => 'New Designation',
            ]);

        $response->assertRedirect(route('admin.accounts.index', ['category_filter' => 'recipients']));
        $this->assertDatabaseHas('users', ['id' => $recipient->id, 'name' => 'Updated Recipient', 'email' => 'updated.recipient@test.local', 'username' => 'R2002']);
        $this->assertDatabaseHas('recipients', ['user_id' => $recipient->id, 'staff_id' => 'R2002', 'unit' => 'CBME', 'designation' => 'New Designation']);

        $page = $this->actingAs($admin)
            ->get(route('admin.accounts.index', ['category_filter' => 'recipients']));

        $page->assertSee('Updated Recipient');
        $page->assertSee('R2002');
        $page->assertSee('CBME');
        $page->assertSee('New Designation');
    }

    public function test_admin_recipient_update_creates_missing_profile_record_and_shows_new_data(): void
    {
        /** @var User $admin */
        $admin = User::factory()->create([
            'username' => 'admin1',
            'role' => User::ROLE_SDS_ADMIN,
            'email_verified_at' => now(),
            'must_change_password' => false,
        ]);

        Unit::create(['name' => 'BIOS', 'type' => Unit::TYPE_OFFICE]);

        /** @var User $recipient */
        $recipient = User::factory()->create([
            'name' => 'Lian Fulgosino',
            'username' => 'ROLD1',
            'email' => 'lian.fulgosino@test.local',
            'role' => User::ROLE_RECIPIENT,
            'email_verified_at' => now(),
            'must_change_password' => false,
        ]);

        $response = $this->actingAs($admin)
            ->put(route('admin.accounts.update', $recipient), [
                'first_name' => 'Lian',
                'middle_name' => '',
                'last_name' => 'Fulgosino',
                'email' => 'lian.updated@test.local',
                'staff_id' => 'R9001',
                'unit' => 'BIOS',
                'designation' => 'Coordinator',
            ]);

        $response->assertRedirect(route('admin.accounts.index', ['category_filter' => 'recipients']));
        $this->assertDatabaseHas('users', ['id' => $recipient->id, 'email' => 'lian.updated@test.local', 'username' => 'R9001']);
        $this->assertDatabaseHas('recipients', ['user_id' => $recipient->id, 'staff_id' => 'R9001', 'unit' => 'BIOS', 'designation' => 'Coordinator']);

        $page = $this->actingAs($admin)
            ->get(route('admin.accounts.index', ['category_filter' => 'recipients']));

        $page->assertSee('Lian Fulgosino');
        $page->assertSee('R9001');
        $page->assertSee('BIOS');
        $page->assertSee('Coordinator');
    }

    public function test_admin_accounts_index_uses_modal_edit_controls_for_students_and_recipients(): void
    {
        /** @var User $admin */
        $admin = User::factory()->create([
            'username' => 'admin1',
            'role' => User::ROLE_SDS_ADMIN,
            'email_verified_at' => now(),
            'must_change_password' => false,
        ]);

        /** @var User $student */
        $student = User::factory()->create([
            'name' => 'Jane Student',
            'email' => 'jane@student.test',
            'role' => User::ROLE_STUDENT,
            'email_verified_at' => now(),
            'must_change_password' => false,
        ]);
        Student::create([
            'user_id' => $student->id,
            'student_id' => '20246002',
            'college' => 'CICT',
            'program' => 'BSCS',
            'year_level' => '2',
            'block' => 2,
        ]);

        /** @var User $recipient */
        $recipient = User::factory()->create([
            'name' => 'John Recipient',
            'email' => 'john@recipient.test',
            'role' => User::ROLE_RECIPIENT,
            'email_verified_at' => now(),
            'must_change_password' => false,
        ]);
        Recipient::create([
            'user_id' => $recipient->id,
            'staff_id' => 'R1002',
            'unit' => 'Administrative',
            'designation' => 'Coordinator',
        ]);

        $response = $this->actingAs($admin)
            ->get(route('admin.accounts.index'));

        $response->assertOk();
        $response->assertSee('editStudentModalOpen');
        $response->assertSee('editRecipientModalOpen');
        $response->assertSee('Edit Student Account');
        $response->assertSee('Edit Recipient Account');
    }

    public function test_admin_accounts_uses_colleges_and_offices_configured_in_system_settings(): void
    {
        $admin = User::factory()->create([
            'role' => User::ROLE_SDS_ADMIN,
            'email_verified_at' => now(),
            'must_change_password' => false,
        ]);

        Unit::create(['name' => 'Configured College', 'type' => Unit::TYPE_COLLEGE]);
        Unit::create(['name' => 'Configured Office', 'type' => Unit::TYPE_OFFICE]);

        $response = $this->actingAs($admin)->get(route('admin.accounts.index'));

        $response->assertOk();
        $response->assertSee('Configured College');
        $response->assertSee('Configured Office');
    }

    public function test_incomplete_recipient_cannot_be_activated(): void
    {
        $admin = User::factory()->create([
            'role' => User::ROLE_SDS_ADMIN,
            'email_verified_at' => now(),
            'must_change_password' => false,
        ]);
        $recipient = User::factory()->create([
            'role' => User::ROLE_RECIPIENT,
            'is_active' => false,
        ]);
        Recipient::create([
            'user_id' => $recipient->id,
            'staff_id' => 'R3001',
            'unit' => '',
            'designation' => '',
        ]);

        $response = $this->actingAs($admin)
            ->patch(route('admin.accounts.reactivate', $recipient));

        $response->assertRedirect(route('admin.accounts.index', ['category_filter' => 'recipients']));
        $response->assertSessionHasErrors('account');
        $this->assertDatabaseHas('users', ['id' => $recipient->id, 'is_active' => false]);

        $page = $this->actingAs($admin)
            ->get(route('admin.accounts.index', ['category_filter' => 'recipients']));

        $page->assertSee('cursor-not-allowed');
    }

    public function test_complete_recipient_can_be_activated(): void
    {
        $admin = User::factory()->create([
            'role' => User::ROLE_SDS_ADMIN,
            'email_verified_at' => now(),
            'must_change_password' => false,
        ]);
        $recipient = User::factory()->create([
            'role' => User::ROLE_RECIPIENT,
            'is_active' => false,
        ]);
        Recipient::create([
            'user_id' => $recipient->id,
            'staff_id' => 'R3002',
            'unit' => 'Configured Department',
            'designation' => 'Coordinator',
        ]);

        $response = $this->actingAs($admin)
            ->patch(route('admin.accounts.reactivate', $recipient));

        $response->assertRedirect(route('admin.accounts.index', ['category_filter' => 'recipients']));
        $this->assertDatabaseHas('users', [
            'id' => $recipient->id,
            'is_active' => true,
        ]);
        $this->assertNotNull($recipient->fresh()->email_verified_at);
    }
}
