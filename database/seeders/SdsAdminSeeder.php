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
                'username' => '12345',
                'role' => User::ROLE_SDS_ADMIN,
            ],
            [
                'name' => '',
                'email' => 'sorsu.support@gmail.com',
                'password' => Hash::make('12345'),
                'must_change_password' => false,
                'is_active' => true,
                'email_verified_at' => now(),
            ]
        );
    }
}