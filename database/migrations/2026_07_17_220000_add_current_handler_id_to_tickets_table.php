<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            if (! Schema::hasColumn('tickets', 'current_handler_id')) {
                DB::statement('ALTER TABLE tickets ADD COLUMN current_handler_id INTEGER NULL');
            }

            return;
        }

        Schema::table('tickets', function (Blueprint $table) {
            if (! Schema::hasColumn('tickets', 'current_handler_id')) {
                $table->foreignId('current_handler_id')->nullable()->after('assigned_to')->constrained('users')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            if (Schema::hasColumn('tickets', 'current_handler_id')) {
                DB::statement('ALTER TABLE tickets DROP COLUMN current_handler_id');
            }

            return;
        }

        Schema::table('tickets', function (Blueprint $table) {
            if (Schema::hasColumn('tickets', 'current_handler_id')) {
                $table->dropConstrainedForeignId('current_handler_id');
            }
        });
    }
};
