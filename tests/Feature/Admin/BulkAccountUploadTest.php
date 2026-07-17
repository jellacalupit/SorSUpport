<?php

namespace Tests\Feature\Admin;

use App\Models\Recipient;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class BulkAccountUploadTest extends TestCase
{
    use RefreshDatabase;

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
            'username' => 'S1001',
            'email' => 'existing@student.test',
            'role' => User::ROLE_STUDENT,
            'email_verified_at' => now(),
            'must_change_password' => false,
        ]);
        Student::create([
            'user_id' => $existingStudent->id,
            'student_id' => 'S1001',
            'department' => 'Technology',
            'course' => 'IT',
            'year_level' => '2nd Year',
            'block' => 'A',
        ]);

        $file = UploadedFile::fake()->createWithContent('accounts.csv', "email,name,student_id,department,course,year_level,block\nnew@student.test,New Student,S2001,Engineering,CS,1st Year,B\nexisting@student.test,Updated Student,S1001,Technology,IT,3rd Year,B\n");

        $response = $this->actingAs($admin)
            ->post(route('admin.accounts.upload.store'), [
                'file' => $file,
            ]);

        $response->assertRedirect(route('admin.accounts.index'));
        $this->assertDatabaseHas('users', ['email' => 'new@student.test', 'role' => User::ROLE_STUDENT, 'is_active' => true]);
        $this->assertDatabaseHas('students', ['student_id' => 'S2001']);
        $this->assertDatabaseHas('users', ['email' => 'existing@student.test', 'role' => User::ROLE_STUDENT, 'is_active' => true]);
        $this->assertDatabaseHas('students', ['student_id' => 'S1001', 'year_level' => '3rd Year']);
    }
}
