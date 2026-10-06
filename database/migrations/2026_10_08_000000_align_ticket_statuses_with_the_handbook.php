<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ticket statuses become Submitted, Needs Clarification, Assigned, In Progress, Escalated,
     * Referred, Resolved and Closed. "Pending" is renamed to "Submitted" and "Rejected" is folded
     * into "Closed" with a closure type that says why.
     */
    public function up(): void
    {
        // Plain strings, so a new status no longer needs a schema change. On SQLite the tickets
        // column is already a plain string (see allow_invalid_classification_on_tickets).
        if (DB::getDriverName() !== 'sqlite') {
            Schema::table('tickets', function (Blueprint $table) {
                $table->string('status', 30)->default('submitted')->change();
            });
        }

        Schema::table('complaints', function (Blueprint $table) {
            $table->string('status', 30)->default('submitted')->change();
        });

        Schema::table('tickets', function (Blueprint $table) {
            $table->string('closure_type', 40)->nullable()->after('closure_reason');
            $table->string('resolution_type', 40)->nullable()->after('closure_type');
            $table->string('referred_to')->nullable()->after('resolution_type');
            $table->timestamp('referred_at')->nullable()->after('referred_to');
            $table->timestamp('clarification_requested_at')->nullable()->after('referred_at');
        });

        DB::table('tickets')->where('status', 'rejected')->update(['status' => 'closed', 'closure_type' => 'invalid']);
        DB::table('tickets')->where('status', 'pending')->update(['status' => 'submitted']);
        DB::table('complaints')->where('status', 'rejected')->update(['status' => 'closed']);
        DB::table('complaints')->where('status', 'pending')->update(['status' => 'submitted']);

        // Give tickets closed before this change a closure type.
        DB::table('tickets')->where('status', 'closed')->whereNull('closure_type')->where('classification', 'invalid')->update(['closure_type' => 'invalid']);
        DB::table('tickets')->where('status', 'closed')->whereNull('closure_type')->where('classification', 'informational')->update(['closure_type' => 'informational']);
        DB::table('tickets')->where('status', 'closed')->whereNull('closure_type')->update(['closure_type' => 'resolved']);
    }

    public function down(): void
    {
        DB::table('tickets')->whereIn('status', ['submitted', 'needs_clarification'])->update(['status' => 'pending']);
        DB::table('tickets')->where('status', 'referred')->update(['status' => 'in_progress']);
        DB::table('complaints')->whereIn('status', ['submitted', 'needs_clarification'])->update(['status' => 'pending']);
        DB::table('complaints')->whereIn('status', ['assigned', 'escalated', 'referred'])->update(['status' => 'in_progress']);

        Schema::table('tickets', function (Blueprint $table) {
            $table->dropColumn(['closure_type', 'resolution_type', 'referred_to', 'referred_at', 'clarification_requested_at']);
        });

        if (DB::getDriverName() !== 'sqlite') {
            Schema::table('tickets', function (Blueprint $table) {
                $table->string('status', 30)->default('pending')->change();
            });
        }

        Schema::table('complaints', function (Blueprint $table) {
            $table->string('status', 30)->default('pending')->change();
        });
    }
};
