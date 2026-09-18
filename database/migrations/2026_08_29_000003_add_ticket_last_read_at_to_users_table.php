<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->json('student_ticket_last_read_at')->nullable()->after('student_ticket_read_ids');
            $table->json('admin_ticket_last_read_at')->nullable()->after('admin_ticket_read_ids');
            $table->json('recipient_ticket_last_read_at')->nullable()->after('recipient_ticket_read_ids');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'student_ticket_last_read_at',
                'admin_ticket_last_read_at',
                'recipient_ticket_last_read_at',
            ]);
        });
    }
};
