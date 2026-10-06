<?php

namespace Database\Seeders;

use App\Models\Recipient;
use App\Models\ComplaintCategory;
use App\Models\Student;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DemoAccountSeeder extends Seeder
{
    public function run(): void
    {
        // The Bulan campus colleges and offices as first configured; the SDS admin maintains them in System Settings.
        $colleges = [
            'CICT' => [
                'description' => 'College of Information and Communications Technology',
                'programs' => [
                    'BSIT' => ['blocks' => 5, 'description' => 'Bachelor of Science in Information Technology'],
                    'BSCS' => ['blocks' => 2, 'description' => 'Bachelor of Science in Computer Science'],
                    'BSIS' => ['blocks' => 2, 'description' => 'Bachelor of Science in Information Systems'],
                ],
            ],
            'CBME' => [
                'description' => 'College of Business Management and Entrepreneurship',
                'programs' => [
                    'BSA' => ['blocks' => 2, 'description' => 'Bachelor of Science in Accountancy'],
                    'BSAIS' => ['blocks' => 4, 'description' => 'Bachelor of Science in Accounting Information System'],
                    'BSE' => ['blocks' => 2, 'description' => 'Bachelor of Science in Entrepreneurship'],
                ],
            ],
        ];

        foreach ($colleges as $collegeName => $details) {
            $college = Unit::updateOrCreate(
                ['name' => $collegeName],
                ['type' => Unit::TYPE_COLLEGE, 'description' => $details['description']]
            );

            foreach ($details['programs'] as $program => $programDetails) {
                $college->programs()->updateOrCreate(
                    ['name' => $program],
                    ['year_level' => 4, 'block' => $programDetails['blocks'], 'description' => $programDetails['description']]
                );
            }

            foreach (['Dean', 'Program Chair', 'Instructor'] as $designation) {
                $college->designations()->updateOrCreate(['name' => $designation]);
            }
        }

        $offices = [
            'Campus Administration' => [
                'description' => 'Handles the overall management, coordination, and administrative operations of the campus.',
                'designations' => ['Campus Director'],
            ],
            'Student Development and Services' => [
                'description' => 'Provides student support and development services that promote student welfare, well-being, and success.',
                'designations' => ['SDS Coordinator'],
            ],
            'Facilities & Maintenance' => [
                'description' => 'Maintains campus buildings, grounds, and equipment.',
                'designations' => ['Maintenance Head'],
            ],
        ];

        foreach ($offices as $officeName => $details) {
            $office = Unit::updateOrCreate(
                ['name' => $officeName],
                ['type' => Unit::TYPE_OFFICE, 'description' => $details['description']]
            );

            foreach ($details['designations'] as $designation) {
                $office->designations()->updateOrCreate(['name' => $designation]);
            }
        }

        $recipientProfiles = [
            ['type' => 'Program Chair', 'unit' => 'CICT'],
            ['type' => 'Instructor', 'unit' => 'CICT'],
            ['type' => 'Instructor', 'unit' => 'CBME'],
            ['type' => 'Dean', 'unit' => 'CICT'],
            ['type' => 'Program Chair', 'unit' => 'CBME'],
            ['type' => 'Dean', 'unit' => 'CBME'],
            ['type' => 'Maintenance Head', 'unit' => 'Facilities & Maintenance'],
            ['type' => 'Campus Director', 'unit' => 'Campus Administration'],
            ['type' => 'SDS Coordinator', 'unit' => 'Student Development and Services'],
            ['type' => 'Instructor', 'unit' => 'CICT'],
        ];

        foreach (range(1, 10) as $number) {
            $isPrimaryRecipient = $number === 1;
            $staffId = $isPrimaryRecipient
                ? '2465'
                : 'REC-' . str_pad((string) $number, 3, '0', STR_PAD_LEFT);
            $profile = $recipientProfiles[$number - 1];
            $user = User::updateOrCreate(
                ['email' => $isPrimaryRecipient ? 'agathanicolecarungcong@sorsu.edu.ph' : 'recipient' . $number . '@sorsu.test'],
                [
                    'name' => $isPrimaryRecipient ? 'Agatha Nicole Varias Carungcong' : $profile['type'] . ' ' . $number,
                    'username' => $staffId,
                    'password' => Hash::make('Welcome@123'),
                    'must_change_password' => false,
                    'role' => User::ROLE_RECIPIENT,
                    'is_active' => true,
                    'email_verified_at' => now(),
                ]
            );

            Recipient::updateOrCreate(
                ['staff_id' => $staffId],
                [
                    'user_id' => $user->id,
                    'staff_id' => $staffId,
                    'unit' => $profile['unit'],
                    'designation' => $profile['type'],
                ]
            );
        }

        foreach (range(1, 3) as $number) {
            $studentId = 'STU-' . str_pad((string) $number, 4, '0', STR_PAD_LEFT);
            $user = User::updateOrCreate(
                ['email' => 'student' . $number . '@sorsu.test'],
                [
                    'name' => 'Student ' . $number,
                    'username' => $studentId,
                    'password' => Hash::make('Welcome@123'),
                    'must_change_password' => false,
                    'role' => User::ROLE_STUDENT,
                    'is_active' => true,
                    'email_verified_at' => now(),
                ]
            );

            Student::updateOrCreate(
                ['user_id' => $user->id],
                [
                    'student_id' => $studentId,
                    'college' => $collegeName = array_keys($colleges)[($number - 1) % count($colleges)],
                    'program' => array_key_first($colleges[$collegeName]['programs']),
                    'year_level' => (string) (($number - 1) % 4 + 1),
                    'block' => '1',
                ]
            );
        }

        $categoryDefinitions = [
            ['name' => 'Academic Concerns', 'description' => 'Grading disputes and faculty-related academic issues.', 'deadline' => 15, 'recipient' => '2465'],
            ['name' => 'Student Welfare and Development', 'description' => 'Concerns handled directly by Student Development Services.', 'deadline' => 15, 'recipient' => null, 'jurisdiction' => 'sds'],
        ];

        foreach ($categoryDefinitions as $definition) {
            ComplaintCategory::updateOrCreate(
                ['name' => $definition['name']],
                [
                    'description' => $definition['description'],
                    'recipient_id' => $definition['recipient'] !== null
                        ? Recipient::where('staff_id', $definition['recipient'])->value('id')
                        : null,
                    'resolution_deadline_days' => $definition['deadline'],
                    'default_jurisdiction' => $definition['jurisdiction'] ?? 'recipient',
                    'is_active' => true,
                ]
            );
        }

        ComplaintCategory::whereNotIn('name', ['Academic Concerns', 'Student Welfare and Development'])->update(['is_active' => false]);
    }
}
