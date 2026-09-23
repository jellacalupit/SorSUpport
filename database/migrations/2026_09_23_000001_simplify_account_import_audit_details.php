<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('audit_logs')
            ->where('action', 'accounts_imported')
            ->where('details', 'like', '%row(s) failed.%')
            ->orderBy('id')
            ->eachById(function (object $auditLog): void {
                $details = preg_replace(
                    '/^(Imported \d+ \w+ account\(s\)); \d+ row\(s\) failed\.$/',
                    '$1.',
                    (string) $auditLog->details
                );

                if ($details !== null && $details !== $auditLog->details) {
                    DB::table('audit_logs')
                        ->where('id', $auditLog->id)
                        ->update(['details' => $details]);
                }
            });
    }

    public function down(): void
    {
        // The original failed-row count is not recoverable from the audit detail.
    }
};
