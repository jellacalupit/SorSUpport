<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('complaint_category_suggested_recipients', function (Blueprint $table) {
            $table->unsignedBigInteger('complaint_category_id');
            $table->unsignedBigInteger('recipient_id');
            $table->foreign('complaint_category_id', 'ccsr_category_fk')->references('id')->on('complaint_categories')->cascadeOnDelete();
            $table->foreign('recipient_id', 'ccsr_recipient_fk')->references('id')->on('recipients')->cascadeOnDelete();
            $table->primary(['complaint_category_id', 'recipient_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('complaint_category_suggested_recipients');
    }
};
