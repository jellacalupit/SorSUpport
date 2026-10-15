<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * An email that could not be sent is tried again a few times before it is given up on.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('email_notifications', function (Blueprint $table) {
            $table->unsignedTinyInteger('attempts')->default(0)->after('status');
            $table->timestamp('next_attempt_at')->nullable()->after('attempts');
        });
    }

    public function down(): void
    {
        Schema::table('email_notifications', function (Blueprint $table) {
            $table->dropColumn(['attempts', 'next_attempt_at']);
        });
    }
};
