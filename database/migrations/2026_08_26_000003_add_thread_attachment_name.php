<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('thread_messages', function (Blueprint $table) {
            $table->string('file_attachment_name')->nullable()->after('file_attachment');
        });
    }

    public function down(): void
    {
        Schema::table('thread_messages', function (Blueprint $table) {
            $table->dropColumn('file_attachment_name');
        });
    }
};
