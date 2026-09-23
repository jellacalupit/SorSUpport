<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('audit_logs')
            ->where('action', 'account_reactivated')
            ->orderBy('id')
            ->eachById(function (object $auditLog): void {
                DB::table('audit_logs')
                    ->where('id', $auditLog->id)
                    ->update([
                        'action' => 'account_activated',
                        'details' => Str::replaceStart('Reactivated ', 'Activated ', $auditLog->details ?? ''),
                    ]);
            });
    }

    public function down(): void
    {
        DB::table('audit_logs')
            ->where('action', 'account_activated')
            ->orderBy('id')
            ->eachById(function (object $auditLog): void {
                DB::table('audit_logs')
                    ->where('id', $auditLog->id)
                    ->update([
                        'action' => 'account_reactivated',
                        'details' => Str::replaceStart('Activated ', 'Reactivated ', $auditLog->details ?? ''),
                    ]);
            });
    }
};
