<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('analytics_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('generated_by')->constrained('users')->cascadeOnDelete();
            $table->string('date_range');
            $table->json('data');
            $table->timestamp('generated_at');
            $table->timestamps();
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('analytics_reports');
    }
};
