<?php

namespace Tests\Feature\Admin;

use App\Models\Complaint;
use App\Models\ComplaintCategory;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardVolumeChartTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_dashboard_shows_volume_chart_card(): void
    {
        /** @var User $admin */
        $admin = User::factory()->create([
            'role' => User::ROLE_SDS_ADMIN,
            'email_verified_at' => now(),
            'must_change_password' => false,
        ]);

        $student = User::factory()->create([
            'role' => User::ROLE_STUDENT,
            'email_verified_at' => now(),
            'must_change_password' => false,
        ]);

        $studentProfile = Student::create([
            'user_id' => $student->id,
            'student_id' => 'S9001',
            'college' => 'IT',
            'program' => 'BSIT',
            'year_level' => '2nd Year',
            'block' => 'A',
        ]);

        $category = ComplaintCategory::create([
            'name' => 'Academic Concerns',
            'resolution_deadline_days' => 7,
            'is_active' => true,
        ]);

        Complaint::create([
            'reference_number' => Complaint::generateReferenceNumber(),
            'student_id' => $studentProfile->id,
            'category_id' => $category->id,
            'subject_title' => 'Grade dispute',
            'description' => 'Need review of final grade.',
            'is_anonymous' => false,
            'status' => Complaint::STATUS_PENDING,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Volume by category')
            ->assertSee('All categories')
            ->assertSee('All months')
            ->assertSee('Academic Concerns')
            ->assertSee((string) now()->year)
            ->assertSee(route('admin.analytics.index'), false);
    }
}
