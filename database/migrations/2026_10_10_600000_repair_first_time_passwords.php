<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * An account that has not set its own password signs in with its ID. Editing the ID used to
 * leave the first-time password as the old ID, so the new ID was rejected. Those accounts get
 * their first-time password set to their current ID. Passwords users chose are not touched.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('users')
            ->where('must_change_password', true)
            ->whereNotNull('username')
            ->orderBy('id')
            ->get(['id', 'username', 'password'])
            ->reject(fn ($user) => Hash::check((string) $user->username, (string) $user->password))
            ->each(fn ($user) => DB::table('users')->where('id', $user->id)->update(['password' => Hash::make((string) $user->username)]));
    }

    public function down(): void
    {
        // The repaired passwords are kept.
    }
};
