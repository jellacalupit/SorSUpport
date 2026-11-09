<?php

use App\Models\Ticket;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('tickets')
            || ! Schema::hasColumn('tickets', 'referred_to')
            || ! Schema::hasColumn('tickets', 'is_codi_referral')) {
            return;
        }

        $normalize = static fn (string $destination): string => preg_replace('/[\s\p{P}\p{S}]+/u', '', mb_strtolower(trim($destination))) ?? '';
        $fullName = $normalize(Ticket::REFERRAL_DESTINATION_CODI);

        DB::table('tickets')
            ->where('is_codi_referral', false)
            ->whereNotNull('referred_to')
            ->select(['id', 'referred_to'])
            ->orderBy('id')
            ->chunkById(500, function ($tickets) use ($normalize, $fullName): void {
                foreach ($tickets as $ticket) {
                    if (! in_array($normalize($ticket->referred_to), [$fullName, 'codi'], true)) {
                        continue;
                    }

                    DB::table('tickets')->where('id', $ticket->id)->update([
                        'referred_to' => Ticket::REFERRAL_DESTINATION_CODI,
                        'is_codi_referral' => true,
                    ]);
                }
            });
    }

    public function down(): void
    {
        // Existing referral text is intentionally not rewritten on rollback.
    }
};
