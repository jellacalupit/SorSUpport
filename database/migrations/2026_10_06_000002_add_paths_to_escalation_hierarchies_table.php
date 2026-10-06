<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A category can have several escalation paths (sets); each path has its own ordered levels.
     */
    public function up(): void
    {
        Schema::table('escalation_hierarchies', function (Blueprint $table): void {
            $table->unsignedSmallInteger('path_number')->default(1)->after('complaint_category_id');
            $table->string('path_name')->nullable()->after('path_number');
        });

        Schema::table('escalation_hierarchies', function (Blueprint $table): void {
            // Added first so the category foreign key always has an index to use.
            $table->unique(
                ['complaint_category_id', 'path_number', 'level', 'recipient_id'],
                'escalation_category_path_level_recipient_unique'
            );
            $table->dropUnique('escalation_category_recipient_level_unique');
        });
    }

    public function down(): void
    {
        Schema::table('escalation_hierarchies', function (Blueprint $table): void {
            $table->unique(
                ['complaint_category_id', 'recipient_id', 'level'],
                'escalation_category_recipient_level_unique'
            );
            $table->dropUnique('escalation_category_path_level_recipient_unique');
        });

        Schema::table('escalation_hierarchies', function (Blueprint $table): void {
            $table->dropColumn(['path_number', 'path_name']);
        });
    }
};
