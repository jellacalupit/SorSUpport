<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('escalation_hierarchies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('complaint_category_id')->constrained('complaint_categories')->cascadeOnDelete();
            $table->unsignedInteger('level');
            $table->foreignId('recipient_id')->constrained('recipients')->cascadeOnDelete();
            $table->timestamps();
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('escalation_hierarchies');
    }
};
