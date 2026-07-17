<?php

namespace App\Imports;

use App\Models\Recipient;
use App\Models\Student;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class AccountsImport implements ToCollection, WithHeadingRow
{
    protected array $processedStudentIds = [];
    protected array $processedStaffIds = [];
    protected int $created = 0;
    protected int $updated = 0;
    protected int $deactivated = 0;
    public function collection(Collection $rows)
    {
        foreach ($rows as $row) {
            if (empty($row['email'])) {
                continue;
            }

            $row = array_map(fn ($value) => is_string($value) ? trim($value) : $value, $row->toArray());

            $studentId = $row['student_id'] ?? null;
            $staffId = $row['staff_id'] ?? null;
            $email = $row['email'];
            $role = null;

            if (! empty($studentId)) {
                $role = User::ROLE_STUDENT;
                $this->processedStudentIds[] = $studentId;
            }

            if (! empty($staffId)) {
                $role = User::ROLE_RECIPIENT;
                $this->processedStaffIds[] = $staffId;
            }

            if (! $role) {
                continue;
            }

            $user = null;

            if ($role === User::ROLE_STUDENT) {
                $student = Student::where('student_id', $studentId)->first();
                $user = $student?->user;
            }

            if ($role === User::ROLE_RECIPIENT) {
                $recipient = Recipient::where('staff_id', $staffId)->first();
                $user = $recipient?->user;
            }

            if (! $user) {
                $user = User::where('email', $email)->first();
            }

            $isNew = false;

            if (! $user) {
                $isNew = true;
                $this->created++;

                $user = User::create([
                    'name' => $row['name'] ?? $email,
                    'username' => $role === User::ROLE_STUDENT ? $studentId : $staffId,
                    'email' => $email,
                    'password' => Hash::make('Welcome@123'),
                    'must_change_password' => true,
                    'role' => $role,
                    'is_active' => true,
                ]);
            } else {
                $this->updated++;

                $user->update([
                    'name' => $row['name'] ?? $user->name,
                    'username' => $role === User::ROLE_STUDENT ? $studentId : $staffId,
                    'email' => $email,
                    'is_active' => true,
                ]);
            }

            if ($isNew) {
                $user->sendEmailVerificationNotification();
            }

            if ($role === User::ROLE_STUDENT) {
                Student::updateOrCreate(
                    ['user_id' => $user->id],
                    [
                        'student_id' => $studentId,
                        'department' => $row['department'] ?? null,
                        'course' => $row['course'] ?? null,
                        'year_level' => $row['year_level'] ?? null,
                        'block' => $row['block'] ?? null,
                    ]
                );
            }

            if ($role === User::ROLE_RECIPIENT) {
                Recipient::updateOrCreate(
                    ['user_id' => $user->id],
                    [
                        'staff_id' => $staffId,
                        'department' => $row['recipient_department'] ?? null,
                        'designation' => $row['designation'] ?? null,
                    ]
                );
            }
        }

        $this->deactivateMissingAccounts();
    }

    private function deactivateMissingAccounts(): void
    {
        if (! empty($this->processedStudentIds)) {
            $studentsToKeep = Student::whereIn('student_id', $this->processedStudentIds)
                ->pluck('user_id')
                ->toArray();

            $deactivated = User::where('role', User::ROLE_STUDENT)
                ->whereNotIn('id', $studentsToKeep)
                ->where('is_active', true)
                ->update(['is_active' => false]);

            $this->deactivated += $deactivated;
        }

        if (! empty($this->processedStaffIds)) {
            $recipientsToKeep = Recipient::whereIn('staff_id', $this->processedStaffIds)
                ->pluck('user_id')
                ->toArray();

            $deactivated = User::where('role', User::ROLE_RECIPIENT)
                ->whereNotIn('id', $recipientsToKeep)
                ->where('is_active', true)
                ->update(['is_active' => false]);

            $this->deactivated += $deactivated;
        }
    }

    public function summary(): array
    {
        return [
            'created' => $this->created,
            'updated' => $this->updated,
            'deactivated' => $this->deactivated,
        ];
    }
}