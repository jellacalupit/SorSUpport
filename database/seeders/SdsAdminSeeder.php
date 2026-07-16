<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class SdsAdminSeeder extends Seeder
{
    /**
     * Seed the SDS Administrator account.
     */
    public function run(): void
    {
        User::updateOrCreate(
            [
                'email' => 'sdsadmin@sorsu.edu.ph',
            ],
            [
                'name' => 'SDS Administrator',
                'username' => 'SDSAdmin',
                'password' => Hash::make('Admin@123'),
                'must_change_password' => true,
                'role' => User::ROLE_SDS_ADMIN,
                'is_active' => true,
                'email_verified_at' => now(),
            ]
        );
    }
}