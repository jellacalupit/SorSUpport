<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('complaints', function (Blueprint $table) {
            $table->string('reference_number')->unique()->after('id');
            $table->enum('status', ['pending', 'in_progress', 'resolved', 'closed'])
                ->default('pending')
                ->after('is_anonymous');
        });
    }

    public function down(): void
    {
        Schema::table('complaints', function (Blueprint $table) {
            $table->dropColumn(['reference_number', 'status']);
        });
    }
};
