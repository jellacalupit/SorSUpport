<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('departments', function (Blueprint $table) {
            $table->text('description')->nullable()->after('name');
        });

        Schema::table('department_courses', function (Blueprint $table) {
            $table->text('description')->nullable()->after('block');
        });
    }

    public function down(): void
    {
        Schema::table('department_courses', function (Blueprint $table) {
            $table->dropColumn('description');
        });

        Schema::table('departments', function (Blueprint $table) {
            $table->dropColumn('description');
        });
    }
};
