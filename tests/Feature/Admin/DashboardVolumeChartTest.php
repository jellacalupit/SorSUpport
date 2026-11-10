<?php

namespace Tests\Feature\Admin;

use App\Models\Complaint;
use App\Models\ComplaintCategory;
use App\Models\Student;
use App\Models\Ticket;
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
            'is_active' => true,
        ]);

        $complaint = Complaint::create([
            'reference_number' => Complaint::generateReferenceNumber(),
            'student_id' => $studentProfile->id,
            'category_id' => $category->id,
            'subject_title' => 'Grade dispute',
            'description' => 'Need review of final grade.',
            'is_anonymous' => false,
            'status' => Complaint::STATUS_SUBMITTED,
        ]);

        Ticket::create([
            'complaint_id' => $complaint->id,
            'status' => Ticket::STATUS_SUBMITTED,
            'classification' => null,
        ]);

        $response = $this->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Volume by category')
            ->assertSee('All categories')
            ->assertSee('All months')
            ->assertSee('Academic Concerns')
            ->assertSee('Unclassified')
            ->assertSee((string) now()->year)
            ->assertSee(route('admin.analytics.index'), false);

        $this->assertSame(1, $response->viewData('classificationBreakdown')['unclassified']);
    }

    public function test_admin_dashboard_shows_ten_most_recent_tickets_regardless_of_age(): void
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
            'student_id' => 'S9002',
            'college' => 'IT',
            'program' => 'BSIT',
            'year_level' => '2nd Year',
            'block' => 'A',
        ]);
        $category = ComplaintCategory::create([
            'name' => 'Academic Concerns',
            'is_active' => true,
        ]);

        $oldestCreatedAt = now()->subYears(2);
        for ($index = 0; $index < 11; $index++) {
            $complaint = Complaint::create([
                'reference_number' => Complaint::generateReferenceNumber(),
                'student_id' => $studentProfile->id,
                'category_id' => $category->id,
                'subject_title' => "Dashboard recent ticket {$index}",
                'description' => 'A dashboard test complaint.',
                'is_anonymous' => false,
                'status' => Complaint::STATUS_SUBMITTED,
            ]);
            $createdAt = $oldestCreatedAt->copy()->addDays($index);
            $complaint->forceFill(['created_at' => $createdAt, 'updated_at' => $createdAt])->save();

            Ticket::create([
                'complaint_id' => $complaint->id,
                'status' => Ticket::STATUS_SUBMITTED,
            ]);
        }

        $response = $this->actingAs($admin)->get(route('admin.dashboard'));

        $response->assertOk()->assertDontSee('Dashboard recent ticket 0');
        for ($index = 1; $index < 11; $index++) {
            $response->assertSee("Dashboard recent ticket {$index}");
        }
    }
}
