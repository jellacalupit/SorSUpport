<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('tickets', 'status')) {
            if (DB::getDriverName() === 'sqlite') {
                DB::statement("UPDATE tickets SET status = 'pending' WHERE status IS NULL OR status = ''");
            } else {
                DB::statement("ALTER TABLE tickets MODIFY status ENUM('pending', 'assigned', 'in_progress', 'resolved', 'rejected', 'closed') DEFAULT 'pending'");
            }
        }

        if (Schema::hasColumn('complaints', 'status')) {
            if (DB::getDriverName() === 'sqlite') {
                DB::statement("UPDATE complaints SET status = 'pending' WHERE status IS NULL OR status = ''");
            } else {
                DB::statement("ALTER TABLE complaints MODIFY status ENUM('pending', 'in_progress', 'resolved', 'rejected', 'closed') DEFAULT 'pending'");
            }
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        if (Schema::hasColumn('tickets', 'status')) {
            DB::statement("ALTER TABLE tickets MODIFY status ENUM('pending', 'in_progress', 'resolved', 'closed') DEFAULT 'pending'");
        }

        if (Schema::hasColumn('complaints', 'status')) {
            DB::statement("ALTER TABLE complaints MODIFY status ENUM('pending', 'in_progress', 'resolved', 'closed') DEFAULT 'pending'");
        }
    }
};
