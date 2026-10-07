<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Email entries in the audit trail used to repeat the whole message. Keep who it went to
     * and the subject, which is what the trail is read for.
     */
    public function up(): void
    {
        DB::table('audit_logs')
            ->where('action', 'email_notification_sent')
            ->where('details', 'like', 'Email sent to %. Subject: "%')
            ->orderBy('id')
            ->each(function ($log): void {
                if (preg_match('/^Email sent to (.+?)\. Subject: "(.*?)"\. Message:/s', (string) $log->details, $parts)) {
                    DB::table('audit_logs')->where('id', $log->id)->update([
                        'details' => sprintf('Email sent to %s: "%s".', $parts[1], $parts[2]),
                    ]);
                }
            });
    }

    public function down(): void
    {
        // The removed wording cannot be restored.
    }
};
