<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('escalation_hierarchies', function (Blueprint $table): void {
            $table->unique(
                ['complaint_category_id', 'recipient_id', 'level'],
                'escalation_category_recipient_level_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::table('escalation_hierarchies', function (Blueprint $table): void {
            $table->dropUnique('escalation_category_recipient_level_unique');
        });
    }
};
