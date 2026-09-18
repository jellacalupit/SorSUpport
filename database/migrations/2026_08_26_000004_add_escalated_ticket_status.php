<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('tickets', 'status') && DB::getDriverName() !== 'sqlite') {
            DB::statement("ALTER TABLE tickets MODIFY status ENUM('pending', 'assigned', 'in_progress', 'escalated', 'resolved', 'rejected', 'closed') DEFAULT 'pending'");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            DB::statement("ALTER TABLE tickets MODIFY status ENUM('pending', 'assigned', 'in_progress', 'resolved', 'rejected', 'closed') DEFAULT 'pending'");
        }
    }
};
