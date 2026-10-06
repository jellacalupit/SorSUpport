<?php

namespace Tests\Feature;

use App\Models\Complaint;
use App\Models\ComplaintCategory;
use App\Models\Ticket;
use App\Models\User;
use Database\Seeders\AnalyticsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class Module10AnalyticsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(AnalyticsSeeder::class);
    }

    public function test_dashboard_loads_successfully()
    {
        /** @var \App\Models\User $admin */
        $admin = User::factory()->create(['role' => User::ROLE_SDS_ADMIN]);

        $response = $this->actingAs($admin)->get(route('admin.analytics.index'));

        $response->assertStatus(200);
        $response->assertSee('Analytics');
        $response->assertSee('Generate Report');
    }

    public function test_analytics_service_returns_expected_values()
    {
        /** @var \App\Models\User $admin */
        $admin = User::factory()->create(['role' => User::ROLE_SDS_ADMIN]);
        $this->seed(AnalyticsSeeder::class);

        $response = $this->actingAs($admin)->get(route('admin.analytics.index'));
        $response->assertStatus(200);
        $response->assertViewHas('totalComplaints');
        $response->assertViewHas('totalTickets');
        $response->assertViewHas('categoryDistribution');
        $response->assertViewHas('resolutionRate');
        $response->assertViewHas('averageResolutionTime');
        $response->assertViewHas('escalationFrequency');
    }

    public function test_date_filtering_works_on_dashboard()
    {
        /** @var \App\Models\User $admin */
        $admin = User::factory()->create(['role' => User::ROLE_SDS_ADMIN]);
        $this->seed(AnalyticsSeeder::class);

        /** @var \App\Models\ComplaintCategory $category */
        $category = ComplaintCategory::query()->firstOrFail();
        $startDate = now()->subMonths(2)->format('Y-m-d');
        $endDate = now()->format('Y-m-d');

        $response = $this->actingAs($admin)->get(route('admin.analytics.index', [
            'start_date' => $startDate,
            'end_date' => $endDate,
            'category_id' => $category->id,
        ]));

        $response->assertStatus(200);
        $response->assertViewHas('totalComplaints');
    }

    public function test_pdf_export_returns_successful_response()
    {
        /** @var \App\Models\User $admin */
        $admin = User::factory()->create(['role' => User::ROLE_SDS_ADMIN]);
        $this->seed(AnalyticsSeeder::class);

        $response = $this->actingAs($admin)->get(route('admin.analytics.export.pdf'));
        $response->assertStatus(200);
        $response->assertHeader('content-type', 'application/pdf');
    }

    public function test_excel_export_returns_successful_response()
    {
        /** @var \App\Models\User $admin */
        $admin = User::factory()->create(['role' => User::ROLE_SDS_ADMIN]);
        $this->seed(AnalyticsSeeder::class);

        $response = $this->actingAs($admin)->get(route('admin.analytics.export.excel'));
        $response->assertStatus(200);
        $response->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }

    public function test_seeder_creates_expected_amount_of_data()
    {
        $this->seed(AnalyticsSeeder::class);

        $complaintCount = Complaint::count();
        $ticketCount = Ticket::count();

        $this->assertGreaterThanOrEqual(30, $complaintCount);
        $this->assertGreaterThanOrEqual(30, $ticketCount);
    }
}
