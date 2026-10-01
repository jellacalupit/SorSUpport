<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->json('admin_ticket_read_ids')->nullable()->after('recipient_notification_read_ids');
            $table->json('recipient_ticket_read_ids')->nullable()->after('admin_ticket_read_ids');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'admin_ticket_read_ids',
                'recipient_ticket_read_ids',
            ]);
        });
    }
};
