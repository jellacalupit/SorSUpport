<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('departments', function (Blueprint $table) {
            $table->string('type')->default('recipient')->after('name');
            $table->dropUnique('departments_name_unique');
            $table->unique(['type', 'name']);
        });

        Schema::create('department_courses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('department_id')->constrained()->cascadeOnDelete();
            $table->string('course');
            $table->unsignedTinyInteger('year_level');
            $table->string('block')->nullable();
            $table->timestamps();
            $table->unique(['department_id', 'course', 'year_level', 'block']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('department_courses');

        Schema::table('departments', function (Blueprint $table) {
            $table->dropUnique('departments_type_name_unique');
            $table->dropColumn('type');
            $table->unique('name');
        });
    }
};