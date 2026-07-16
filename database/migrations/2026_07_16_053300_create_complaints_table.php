<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('complaints', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->constrained('complaint_categories')->cascadeOnDelete();
            $table->string('subject_title');
            $table->string('personnel_involved')->nullable();
            $table->text('description');
            $table->string('file_attachment')->nullable();
            $table->boolean('is_anonymous')->default(false);
            $table->timestamps();
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('complaints');
    }
};
