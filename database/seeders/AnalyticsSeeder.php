<?php

namespace Database\Seeders;

use App\Models\Complaint;
use App\Models\ComplaintCategory;
use App\Models\Recipient;
use App\Models\Student;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AnalyticsSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::query()->firstWhere('role', User::ROLE_SDS_ADMIN);

        Ticket::query()
            ->where('status', Ticket::STATUS_SUBMITTED)
            ->update([
                'status' => Ticket::STATUS_ASSIGNED,
                'classification' => Ticket::CLASSIFICATION_NEEDS_RESOLUTION,
                'jurisdiction' => Ticket::JURISDICTION_RECIPIENT,
                'current_handler_id' => DB::raw('assigned_to'),
                'acknowledged_at' => now(),
            ]);

        $recipients = Recipient::query()->with('user')->get();
        if ($recipients->isEmpty()) {
            $recipients = collect();
            foreach (range(1, 3) as $index) {
                $user = User::create([
                    'name' => 'Recipient ' . $index,
                    'username' => 'recipient' . $index,
                    'email' => 'recipient' . $index . '@example.com',
                    'password' => bcrypt('password'),
                    'must_change_password' => false,
                    'role' => User::ROLE_RECIPIENT,
                    'is_active' => true,
                    'email_verified_at' => now(),
                ]);

                $recipients->push(Recipient::create([
                    'user_id' => $user->id,
                    'staff_id' => 'REC' . str_pad($index, 3, '0', STR_PAD_LEFT),
                    'unit' => 'Office ' . $index,
                    'designation' => 'Officer',
                ]));
            }
        }

        $categories = ComplaintCategory::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        if ($categories->isEmpty()) {
            $defaultCategories = [
                'Academic Concerns',
                'Administrative Concerns',
                'Faculty/Staff Conduct',
                'Facilities and Services',
                'Student Welfare Concerns',
                'Organizational/Student Council Concerns',
            ];

            foreach ($defaultCategories as $index => $name) {
                $category = ComplaintCategory::create([
                    'name' => $name,
                    'description' => 'Seeded analytics category',
                    'resolution_deadline_days' => 5 + ($index % 4),
                    'is_active' => true,
                ]);

                $categories->push($category);
            }
        }

        $students = Student::query()
            ->where('student_id', 'like', 'STU-%')
            ->with('user')
            ->limit(20)
            ->get();
        if ($students->isEmpty()) {
            $students = collect();
            foreach (range(1, 20) as $index) {
                $email = 'student' . $index . '@example.com';
                $user = User::query()->firstOrCreate(
                    ['email' => $email],
                    [
                    'name' => 'Student ' . $index,
                    'username' => 'student' . $index,
                    'password' => bcrypt('password'),
                    'must_change_password' => false,
                    'role' => User::ROLE_STUDENT,
                    'is_active' => true,
                    'email_verified_at' => now(),
                    ]
                );

                $students->push(Student::query()->firstOrCreate(
                    ['user_id' => $user->id],
                    [
                    'user_id' => $user->id,
                    'student_id' => 'STU' . str_pad($index, 4, '0', STR_PAD_LEFT),
                    'college' => 'College ' . ($index % 4 + 1),
                    'program' => 'Program ' . ($index % 3 + 1),
                    'year_level' => (string) (($index % 4) + 1),
                    'block' => (string) (($index % 4) + 1),
                    ]
                ));
            }
        }

        $statuses = [
            Ticket::STATUS_ASSIGNED,
            Ticket::STATUS_IN_PROGRESS,
            Ticket::STATUS_RESOLVED,
            Ticket::STATUS_CLOSED,
        ];

        $realisticComplaints = [
            'Academic Concerns' => [
                'subject' => 'Request to review an incorrect final grade',
                'description' => 'The final grade posted for a major subject does not match the student\'s documented assessment results and needs verification.',
            ],
            'Administrative Concerns' => [
                'subject' => 'Delayed release of certificate of enrollment',
                'description' => 'A certificate of enrollment requested through the registrar has not been released within the stated processing period.',
            ],
            'Faculty/Staff Conduct' => [
                'subject' => 'Concern about repeated discourteous communication',
                'description' => 'The student is requesting assistance regarding repeated discourteous communication encountered while seeking academic support.',
            ],
            'Facilities and Services' => [
                'subject' => 'Air-conditioning issue in computer laboratory',
                'description' => 'The computer laboratory has had an ongoing air-conditioning problem that affects scheduled classes and equipment use.',
            ],
            'Student Welfare Concerns' => [
                'subject' => 'Request for guidance support and referral',
                'description' => 'The student is requesting a confidential guidance consultation and information about available student support services.',
            ],
            'Organizational/Student Council Concerns' => [
                'subject' => 'Request for clarification on student organization fund usage',
                'description' => 'The student is requesting clarification about the reported use of organization funds and the process for reviewing the supporting records.',
            ],
        ];

        $months = collect(range(1, 6));
        $count = 0;
        $organizationalCategory = $categories->firstWhere('name', 'Organizational/Student Council Concerns');

        foreach ($months as $month) {
            foreach (range(1, 8) as $index) {
                $student = $students->random();
                $category = $count < 5 && $organizationalCategory
                    ? $organizationalCategory
                    : $categories->random();
                $scenario = $realisticComplaints[$category->name] ?? [
                    'subject' => 'Request for assistance with a student concern',
                    'description' => 'The student is requesting assistance and a clear resolution from the responsible office.',
                ];
                $complaintDate = now()->subMonths(rand(0, 5))->subDays(rand(0, 28));

                $complaint = Complaint::create([
                    'reference_number' => Complaint::generateReferenceNumber(),
                    'student_id' => $student->id,
                    'category_id' => $category->id,
                    'subject_title' => $scenario['subject'],
                    'personnel_involved' => 'Assigned office representative',
                    'description' => $scenario['description'],
                    'is_anonymous' => false,
                    'created_at' => $complaintDate,
                    'updated_at' => $complaintDate,
                ]);

                $status = $count < 2
                    ? Ticket::STATUS_SUBMITTED
                    : Arr::random($statuses);
                $resolvedAt = null;
                $closedAt = null;
                $ticketCreatedAt = $complaintDate->copy()->addHours(1);
                $timeline = collect([]);

                if ($status === Ticket::STATUS_SUBMITTED) {
                    $timeline->push(['status' => Ticket::STATUS_SUBMITTED, 'at' => $ticketCreatedAt]);
                }

                if ($status === Ticket::STATUS_ASSIGNED) {
                    $timeline->push(['status' => Ticket::STATUS_ASSIGNED, 'at' => $ticketCreatedAt]);
                }

                if ($status === Ticket::STATUS_IN_PROGRESS) {
                    $timeline->push(['status' => Ticket::STATUS_ASSIGNED, 'at' => $ticketCreatedAt]);
                    $timeline->push(['status' => Ticket::STATUS_IN_PROGRESS, 'at' => $ticketCreatedAt->copy()->addDays(1)]);
                }

                if ($status === Ticket::STATUS_IN_PROGRESS) {
                    $timeline->push(['status' => Ticket::STATUS_ASSIGNED, 'at' => $ticketCreatedAt]);
                    $timeline->push(['status' => Ticket::STATUS_IN_PROGRESS, 'at' => $ticketCreatedAt->copy()->addDays(1)]);
                }

                if ($status === Ticket::STATUS_RESOLVED) {
                    $resolvedAt = $ticketCreatedAt->copy()->addDays(rand(1, 6));
                    $timeline->push(['status' => Ticket::STATUS_ASSIGNED, 'at' => $ticketCreatedAt]);
                    $timeline->push(['status' => Ticket::STATUS_IN_PROGRESS, 'at' => $ticketCreatedAt->copy()->addDay()]);
                    $timeline->push(['status' => Ticket::STATUS_RESOLVED, 'at' => $resolvedAt]);
                }

                if ($status === Ticket::STATUS_CLOSED) {
                    $resolvedAt = $ticketCreatedAt->copy()->addDays(rand(1, 5));
                    $closedAt = $resolvedAt->copy()->addDays(rand(1, 3));
                    $timeline->push(['status' => Ticket::STATUS_ASSIGNED, 'at' => $ticketCreatedAt]);
                    $timeline->push(['status' => Ticket::STATUS_IN_PROGRESS, 'at' => $ticketCreatedAt->copy()->addDay()]);
                    $timeline->push(['status' => Ticket::STATUS_RESOLVED, 'at' => $resolvedAt]);
                    $timeline->push(['status' => Ticket::STATUS_CLOSED, 'at' => $closedAt]);
                }

                Ticket::create([
                    'complaint_id' => $complaint->id,
                    'status' => $status,
                    'assigned_to' => $recipients->random()->user_id,
                    'current_handler_id' => $recipients->random()->user_id,
                    'deadline' => $complaintDate->copy()->addDays($category->resolution_deadline_days),
                    'acknowledged_at' => $ticketCreatedAt->copy()->addHours(3),
                    'resolved_at' => $resolvedAt,
                    'closed_at' => $closedAt,
                    'created_at' => $ticketCreatedAt,
                    'updated_at' => $timeline->last()['at'] ?? $ticketCreatedAt,
                ]);

                $count++;
                if ($count >= 50) {
                    break 2;
                }
            }
        }
    }
}
