<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Academic Concerns and Registrar and Records overlap the more specific categories, so they are
 * removed. A category that a ticket was already filed under is kept, so no ticket loses it.
 */
return new class extends Migration
{
    public function up(): void
    {
        $categoryIds = DB::table('complaint_categories')
            ->whereIn('name', ['Academic Concerns', 'Registrar and Records'])
            ->whereNotExists(fn ($query) => $query->selectRaw('1')
                ->from('complaints')
                ->whereColumn('complaints.category_id', 'complaint_categories.id'))
            ->pluck('id');

        if ($categoryIds->isEmpty()) {
            return;
        }

        DB::table('complaint_category_suggested_recipients')->whereIn('complaint_category_id', $categoryIds)->delete();
        DB::table('escalation_hierarchies')->whereIn('complaint_category_id', $categoryIds)->delete();
        DB::table('complaint_categories')->whereIn('id', $categoryIds)->delete();
    }

    public function down(): void
    {
        // The deleted categories are not restored; add them again in System Settings if needed.
    }
};
