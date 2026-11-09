<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->boolean('is_codi_referral')->default(false);
        });

        DB::table('tickets')
            ->where('referred_to', 'Committee on Decorum and Investigation')
            ->update(['is_codi_referral' => true]);
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropColumn('is_codi_referral');
        });
    }
};
