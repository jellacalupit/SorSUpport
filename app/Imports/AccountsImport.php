<?php

namespace App\Imports;

use App\Models\User;
use App\Models\Student;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class AccountsImport implements ToCollection, WithHeadingRow
{
    public function collection(Collection $rows)
    {
        foreach ($rows as $row) {

            // Skip empty rows
            if (empty($row['email'])) {
                continue;
            }

            /*
            |--------------------------------------------------------------------------
            | Create or Update User
            |--------------------------------------------------------------------------
            */

            $user = User::where('email', $row['email'])->first();

            if (! $user) {

                // New account
                $user = User::create([
                    'name' => $row['name'],
                    'username' => $row['student_id'],
                    'email' => $row['email'],
                    'password' => Hash::make('Welcome@123'),
                    'must_change_password' => true,
                    'role' => User::ROLE_STUDENT,
                    'is_active' => true,
                ]);

            } else {

                // Existing account
                $user->update([
                    'name' => $row['name'],
                    'username' => $row['student_id'],
                    'email' => $row['email'],
                ]);

            }

            /*
            |--------------------------------------------------------------------------
            | Create or Update Student Profile
            |--------------------------------------------------------------------------
            */

            Student::updateOrCreate(

                [
                    'user_id' => $user->id,
                ],

                [
                    'student_id' => $row['student_id'],
                    'department' => $row['department'],
                    'course' => $row['course'],
                    'year_level' => $row['year_level'],
                    'block' => $row['block'],
                ]

            );

        }
    }
}