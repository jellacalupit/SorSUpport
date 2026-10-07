<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tickets no longer carry a resolution deadline. Some concerns need more investigation than a
 * fixed number of days allows, so the SDS admin escalates an unresolved ticket by hand instead.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('complaint_categories', 'resolution_deadline_days')) {
            Schema::table('complaint_categories', function (Blueprint $table): void {
                $table->dropColumn('resolution_deadline_days');
            });
        }

        if (Schema::hasColumn('tickets', 'deadline')) {
            Schema::table('tickets', function (Blueprint $table): void {
                $table->dropColumn('deadline');
            });
        }
    }

    public function down(): void
    {
        Schema::table('complaint_categories', function (Blueprint $table): void {
            $table->unsignedInteger('resolution_deadline_days')->default(1);
        });

        Schema::table('tickets', function (Blueprint $table): void {
            $table->dateTime('deadline')->nullable();
        });
    }
};
