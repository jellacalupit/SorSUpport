<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Complaint;
use App\Models\ComplaintCategory;
use App\Models\Student;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AnalyticsDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_tabs_render_without_ticket_text_and_show_sensitive_count(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_SDS_ADMIN]);
        $student = $this->student('PRIVATE_DASHBOARD_IDENTITY');
        $regular = ComplaintCategory::create(['name' => 'Student Services', 'is_active' => true]);
        $sensitive = ComplaintCategory::create(['name' => 'Sensitive concern category', 'is_active' => true, 'is_sensitive' => true]);
        $this->ticket($student, $regular, Ticket::STATUS_IN_PROGRESS, Ticket::CLASSIFICATION_NEEDS_RESOLUTION, [
            'subject_title' => 'PRIVATE_DASHBOARD_SUBJECT',
            'description' => 'PRIVATE_DASHBOARD_DESCRIPTION',
            'personnel_involved' => 'PRIVATE_DASHBOARD_PERSONNEL',
        ]);
        $this->ticket($student, $sensitive, Ticket::STATUS_IN_PROGRESS, Ticket::CLASSIFICATION_NEEDS_RESOLUTION, [
            'subject_title' => 'PRIVATE_SENSITIVE_DASHBOARD_SUBJECT',
            'description' => 'PRIVATE_SENSITIVE_DASHBOARD_DESCRIPTION',
            'personnel_involved' => 'PRIVATE_SENSITIVE_DASHBOARD_PERSONNEL',
            'is_anonymous' => true,
        ]);

        $response = $this->actingAs($admin)->get(route('admin.analytics.index'))->assertOk();

        foreach (['Overview', 'Response and resolution', 'Staff and offices', 'Students and feedback'] as $heading) {
            $response->assertSee($heading);
        }
        $response->assertSee('Sensitive categories (not broken down): 1')
            ->assertSee('First staff reply (SDS or recipient)')
            ->assertDontSee('Common subjects')
            ->assertDontSee('PRIVATE_DASHBOARD_SUBJECT')
            ->assertDontSee('PRIVATE_DASHBOARD_DESCRIPTION')
            ->assertDontSee('PRIVATE_DASHBOARD_PERSONNEL')
            ->assertDontSee('PRIVATE_SENSITIVE_DASHBOARD_SUBJECT')
            ->assertDontSee('PRIVATE_SENSITIVE_DASHBOARD_DESCRIPTION')
            ->assertDontSee('PRIVATE_SENSITIVE_DASHBOARD_PERSONNEL')
            ->assertDontSee('PRIVATE_DASHBOARD_IDENTITY');
    }

    public function test_non_admin_users_cannot_open_analytics_dashboard(): void
    {
        foreach ([User::ROLE_STUDENT, User::ROLE_RECIPIENT] as $role) {
            $user = User::factory()->create(['role' => $role]);

            $this->actingAs($user)->get(route('admin.analytics.index'))->assertForbidden();
        }
    }

    public function test_empty_analytics_dashboard_shows_friendly_empty_states_in_every_tab(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_SDS_ADMIN]);

        $response = $this->actingAs($admin)->get(route('admin.analytics.index'))->assertOk();
        foreach ([
            'Overview',
            'Response and resolution',
            'Staff and offices',
            'Students and feedback',
            'No ticket volume data in this period.',
            'No tickets in this period.',
            'No category totals in this period.',
            'No office assignments in this period.',
            'No handling-time data in this period.',
            'No outcome data in this period.',
            'No office performance data in this period.',
            'No staff performance data in this period.',
            'No college breakdown data in this period.',
            'No ticket origin data in this period.',
            'No satisfaction ratings in this period.',
        ] as $expected) {
            $response->assertSee($expected);
        }
    }

    public function test_escalation_kpi_and_card_use_the_same_needs_resolution_rate(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_SDS_ADMIN]);
        $student = $this->student('ESCALATION-DASHBOARD-ID');
        $category = ComplaintCategory::create(['name' => 'Student Services', 'is_active' => true]);

        foreach (range(1, 4) as $index) {
            $ticket = $this->ticket($student, $category, Ticket::STATUS_IN_PROGRESS, Ticket::CLASSIFICATION_NEEDS_RESOLUTION);
            if ($index <= 2) {
                AuditLog::log($ticket->id, 'ticket_escalated', $admin->id);
            }
        }
        $informational = $this->ticket($student, $category, Ticket::STATUS_SUBMITTED, Ticket::CLASSIFICATION_INFORMATIONAL);
        AuditLog::log($informational->id, 'ticket_escalated', $admin->id);

        $response = $this->actingAs($admin)->get(route('admin.analytics.index'))->assertOk();
        $response->assertSee('50.0%')
            ->assertSee('2 of 4 needs-resolution')
            ->assertSee('Escalation rate: 2 of 4 needs-resolution tickets (50.0%).');
    }

    private function student(string $studentId): User
    {
        $user = User::factory()->create(['role' => User::ROLE_STUDENT]);
        Student::create([
            'user_id' => $user->id,
            'student_id' => $studentId,
            'college' => 'CICT',
            'program' => 'BSIT',
            'year_level' => '1',
            'block' => 'A',
        ]);

        return $user;
    }

    private function ticket(User $student, ComplaintCategory $category, string $status, string $classification, array $attributes = []): Ticket
    {
        $complaint = Complaint::create([
            'reference_number' => Complaint::generateReferenceNumber(),
            'student_id' => $student->student->id,
            'category_id' => $category->id,
            'subject_title' => $attributes['subject_title'] ?? 'Analytics fixture subject',
            'description' => $attributes['description'] ?? 'Analytics fixture description.',
            'personnel_involved' => $attributes['personnel_involved'] ?? 'Not applicable',
            'is_anonymous' => $attributes['is_anonymous'] ?? false,
            'status' => $status,
        ]);

        return Ticket::create([
            'complaint_id' => $complaint->id,
            'status' => $status,
            'classification' => $classification,
            'resolved_at' => $status === Ticket::STATUS_RESOLVED ? now() : null,
            'satisfaction_rating' => $attributes['satisfaction_rating'] ?? null,
        ] + collect($attributes)->except(['subject_title', 'description', 'personnel_involved', 'is_anonymous', 'satisfaction_rating'])->all());
    }
}
