<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('complaint_categories', function (Blueprint $table) {
            // Sensitive categories (e.g. harassment) get restricted visibility.
            $table->boolean('is_sensitive')->default(false)->after('is_active');
            // Whether a student may submit under this category without showing their identity.
            $table->boolean('allows_hidden_identity')->default(true)->after('is_sensitive');
        });
    }

    public function down(): void
    {
        Schema::table('complaint_categories', function (Blueprint $table) {
            $table->dropColumn(['is_sensitive', 'allows_hidden_identity']);
        });
    }
};
