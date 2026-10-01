<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $users = DB::table('users')->get();

        foreach ($users as $user) {
            $role = $user->role;

            if ($role === 'student') {
                $studentId = DB::table('students')->where('user_id', $user->id)->value('student_id');
                if ($studentId) {
                    DB::table('users')->where('id', $user->id)->update(['username' => $studentId]);
                }
            }

            if ($role === 'sds_admin') {
                $recipientExists = DB::table('recipients')->where('user_id', $user->id)->exists();
                if (! $recipientExists) {
                    DB::table('recipients')->insert([
                        'user_id' => $user->id,
                        'staff_id' => $user->username ?: 'SDS-'.str_pad((string) $user->id, 4, '0', STR_PAD_LEFT),
                        'department' => $user->department ?: null,
                        'designation' => $user->designation ?: null,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                } else {
                    DB::table('recipients')->where('user_id', $user->id)->update([
                        'staff_id' => DB::table('recipients')->where('user_id', $user->id)->value('staff_id') ?: $user->username ?: 'SDS-'.str_pad((string) $user->id, 4, '0', STR_PAD_LEFT),
                        'department' => DB::table('recipients')->where('user_id', $user->id)->value('department') ?: ($user->department ?: null),
                        'designation' => DB::table('recipients')->where('user_id', $user->id)->value('designation') ?: ($user->designation ?: null),
                        'updated_at' => now(),
                    ]);
                }
            }
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['department', 'designation']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('department')->nullable()->after('username');
            $table->string('designation')->nullable()->after('department');
        });

        $recipients = DB::table('recipients')->get();
        foreach ($recipients as $recipient) {
            $user = DB::table('users')->where('id', $recipient->user_id)->first();
            if ($user && $user->role === 'sds_admin') {
                DB::table('users')->where('id', $recipient->user_id)->update([
                    'department' => $recipient->department,
                    'designation' => $recipient->designation,
                ]);
            }
        }
    }
};
