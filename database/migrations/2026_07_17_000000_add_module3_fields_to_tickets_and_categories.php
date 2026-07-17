<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $driver = DB::getDriverName();

        if (! Schema::hasColumn('tickets', 'jurisdiction')) {
            if ($driver === 'sqlite') {
                DB::statement('ALTER TABLE tickets ADD COLUMN jurisdiction VARCHAR(255) NULL');
            } else {
                Schema::table('tickets', function (Blueprint $table) {
                    $table->enum('jurisdiction', ['sds', 'recipient'])->nullable();
                });
            }
        }

        if (! Schema::hasColumn('tickets', 'closure_reason')) {
            if ($driver === 'sqlite') {
                DB::statement('ALTER TABLE tickets ADD COLUMN closure_reason TEXT NULL');
            } else {
                Schema::table('tickets', function (Blueprint $table) {
                    $table->text('closure_reason')->nullable();
                });
            }
        }

        if (! Schema::hasColumn('tickets', 'forwarded_at')) {
            if ($driver === 'sqlite') {
                DB::statement('ALTER TABLE tickets ADD COLUMN forwarded_at DATETIME NULL');
            } else {
                Schema::table('tickets', function (Blueprint $table) {
                    $table->timestamp('forwarded_at')->nullable();
                });
            }
        }

        if (! Schema::hasColumn('tickets', 'forwarded_to')) {
            if ($driver === 'sqlite') {
                DB::statement('ALTER TABLE tickets ADD COLUMN forwarded_to BIGINT UNSIGNED NULL');
            } else {
                Schema::table('tickets', function (Blueprint $table) {
                    $table->foreignId('forwarded_to')->nullable()->constrained('recipients')->nullOnDelete();
                });
            }
        }

        if (! Schema::hasColumn('complaint_categories', 'default_jurisdiction')) {
            if ($driver === 'sqlite') {
                DB::statement("ALTER TABLE complaint_categories ADD COLUMN default_jurisdiction VARCHAR(255) NULL");
            } else {
                Schema::table('complaint_categories', function (Blueprint $table) {
                    $table->enum('default_jurisdiction', ['sds', 'recipient'])->nullable();
                });
            }
        }
    }

    public function down(): void
    {
        $driver = DB::getDriverName();

        if (Schema::hasColumn('complaint_categories', 'default_jurisdiction')) {
            if ($driver === 'sqlite') {
                DB::statement('ALTER TABLE complaint_categories DROP COLUMN default_jurisdiction');
            } else {
                Schema::table('complaint_categories', function (Blueprint $table) {
                    $table->dropColumn('default_jurisdiction');
                });
            }
        }

        foreach (['jurisdiction', 'closure_reason', 'forwarded_at', 'forwarded_to'] as $column) {
            if (Schema::hasColumn('tickets', $column)) {
                if ($driver === 'sqlite') {
                    DB::statement('ALTER TABLE tickets DROP COLUMN ' . $column);
                } else {
                    Schema::table('tickets', function (Blueprint $table) use ($column) {
                        $table->dropColumn($column);
                    });
                }
            }
        }
    }
};
