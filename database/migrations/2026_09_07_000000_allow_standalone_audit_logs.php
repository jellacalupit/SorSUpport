<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audit_logs', function (Blueprint $table): void {
            $table->dropForeign(['ticket_id']);
            $table->foreignId('ticket_id')->nullable()->change();
            $table->foreign('ticket_id')->references('id')->on('tickets')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('audit_logs', function (Blueprint $table): void {
            $table->dropForeign(['ticket_id']);
            $table->foreignId('ticket_id')->nullable(false)->change();
            $table->foreign('ticket_id')->references('id')->on('tickets')->cascadeOnDelete();
        });
    }
};