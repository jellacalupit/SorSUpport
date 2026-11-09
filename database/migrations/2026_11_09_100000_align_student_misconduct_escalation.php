<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['complaint_categories', 'escalation_hierarchies'] as $table) {
            if (! Schema::hasTable($table)) {
                return;
            }
        }

        foreach ([
            'complaint_categories' => ['name'],
            'escalation_hierarchies' => ['complaint_category_id', 'path_name', 'level', 'recipient_id'],
        ] as $table => $columns) {
            foreach ($columns as $column) {
                if (! Schema::hasColumn($table, $column)) {
                    return;
                }
            }
        }

        $categoryId = DB::table('complaint_categories')
            ->where('name', 'Complaint Against a Student')
            ->value('id');

        if (! $categoryId) {
            return;
        }

        $canRemoveStaleSuggestion = Schema::hasTable('complaint_category_suggested_recipients')
            && Schema::hasColumn('complaint_category_suggested_recipients', 'complaint_category_id')
            && Schema::hasColumn('complaint_category_suggested_recipients', 'recipient_id')
            && Schema::hasTable('recipients')
            && Schema::hasColumn('recipients', 'staff_id');

        DB::transaction(function () use ($categoryId, $canRemoveStaleSuggestion): void {
            DB::table('escalation_hierarchies')
                ->where('complaint_category_id', $categoryId)
                ->whereIn('path_name', ['CBME', 'CICT'])
                ->where('level', '>', 2)
                ->delete();

            if (! $canRemoveStaleSuggestion) {
                return;
            }

            $campusDirectorId = DB::table('recipients')->where('staff_id', '200043')->value('id');

            if ($campusDirectorId && ! DB::table('escalation_hierarchies')
                ->where('complaint_category_id', $categoryId)
                ->where('recipient_id', $campusDirectorId)
                ->exists()) {
                DB::table('complaint_category_suggested_recipients')
                    ->where('complaint_category_id', $categoryId)
                    ->where('recipient_id', $campusDirectorId)
                    ->delete();
            }
        });
    }

    public function down(): void
    {
        // The removed escalation level is intentionally not restored.
    }
};
