<?php

namespace Database\Seeders;

use App\Models\Recipient;
use App\Models\ComplaintCategory;
use App\Models\Student;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DemoAccountSeeder extends Seeder
{
    public function run(): void
    {
        $recipientProfiles = [
            ['type' => 'Instructor', 'department' => 'College of Education'],
            ['type' => 'Instructor', 'department' => 'College of Information and Computing Technology'],
            ['type' => 'Program Chair', 'department' => 'College of Information and Computing Technology'],
            ['type' => 'Dean', 'department' => 'College of Information and Computing Technology'],
            ['type' => 'Office Head', 'department' => 'Registrar Office'],
            ['type' => 'Office Head', 'department' => 'Guidance Office'],
            ['type' => 'Student Organization', 'department' => 'Supreme Student Council'],
            ['type' => 'Campus Director', 'department' => 'Bulan Campus'],
            ['type' => 'Office Head', 'department' => 'Student Development Services'],
            ['type' => 'Program Chair', 'department' => 'College of Arts and Sciences'],
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
                    'department' => $isPrimaryRecipient ? 'CICT' : $profile['department'],
                    'designation' => $isPrimaryRecipient ? 'Program Chair' : $profile['type'],
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
                    'department' => 'College ' . (($number - 1) % 3 + 1),
                    'course' => 'BS Information Technology',
                    'year_level' => (string) (($number - 1) % 4 + 1),
                    'block' => chr(64 + $number),
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
