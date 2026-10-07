<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * 1. Gender-Based Sexual Harassment and Gender and Discrimination Concerns go to the same
 *    people, so they become one category. Any ticket filed under the second moves to the first.
 * 2. Each category's suggested recipients also list everyone on its escalation paths.
 *
 * Each change is written to the audit trail under the SDS admin, as saving it in System
 * Settings would record it.
 */
return new class extends Migration
{
    private const MERGED_NAME = 'Gender-Based Harassment and Discrimination';

    public function up(): void
    {
        $admin = DB::table('users')->where('role', 'sds_admin')->orderByRaw("username = '230515' desc")->orderBy('id')->value('id');
        $updated = [];

        $harassment = DB::table('complaint_categories')->where('name', 'Gender-Based Sexual Harassment')->value('id');
        $discrimination = DB::table('complaint_categories')->where('name', 'Gender and Discrimination Concerns')->value('id');

        if ($harassment && $discrimination) {
            DB::table('complaint_categories')->where('id', $harassment)->update([
                'name' => self::MERGED_NAME,
                'description' => 'Unwelcome sexual remarks, advances, or acts, in person or online, and unfair treatment because of gender, sexual orientation, gender identity or expression, or similar grounds.',
                'is_sensitive' => true,
                'allows_hidden_identity' => true,
                'updated_at' => now(),
            ]);

            $mergedRecipients = DB::table('complaint_category_suggested_recipients')
                ->whereIn('complaint_category_id', [$harassment, $discrimination])
                ->distinct()
                ->pluck('recipient_id');
            DB::table('complaint_category_suggested_recipients')->whereIn('complaint_category_id', [$harassment, $discrimination])->delete();
            DB::table('complaint_category_suggested_recipients')->insert($mergedRecipients->map(fn ($id) => [
                'complaint_category_id' => $harassment,
                'recipient_id' => $id,
            ])->all());

            DB::table('complaints')->where('category_id', $discrimination)->update(['category_id' => $harassment]);
            DB::table('escalation_hierarchies')->where('complaint_category_id', $discrimination)->delete();
            DB::table('complaint_categories')->where('id', $discrimination)->delete();

            $this->audit($admin, 'category_deleted', 'Deleted complaint category "Gender and Discrimination Concerns".');
            $updated[$harassment] = self::MERGED_NAME;
        }

        // Everyone on a category's escalation paths is also a suggested recipient.
        $missing = DB::table('escalation_hierarchies as steps')
            ->leftJoin('complaint_category_suggested_recipients as suggested', fn ($join) => $join
                ->on('suggested.complaint_category_id', '=', 'steps.complaint_category_id')
                ->on('suggested.recipient_id', '=', 'steps.recipient_id'))
            ->whereNull('suggested.recipient_id')
            ->select('steps.complaint_category_id', 'steps.recipient_id')
            ->distinct()
            ->get();

        foreach ($missing as $row) {
            DB::table('complaint_category_suggested_recipients')->insert([
                'complaint_category_id' => $row->complaint_category_id,
                'recipient_id' => $row->recipient_id,
            ]);
            $updated[$row->complaint_category_id] ??= DB::table('complaint_categories')->where('id', $row->complaint_category_id)->value('name');
        }

        asort($updated);
        foreach ($updated as $name) {
            $this->audit($admin, 'category_updated', sprintf('Updated complaint category "%s".', $name));
        }
    }

    protected function audit(?int $admin, string $action, string $details): void
    {
        DB::table('audit_logs')->insert([
            'ticket_id' => null,
            'performed_by' => $admin,
            'action' => $action,
            'details' => $details,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        // The merged category is kept; split it again in System Settings if needed.
    }
};
