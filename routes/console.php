<?php

use App\Models\Ticket;
use App\Services\TicketEscalationService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('tickets:escalate', function (TicketEscalationService $service) {
    $tickets = Ticket::query()
        ->where('deadline', '<', now())
        ->whereNotIn('status', [Ticket::STATUS_RESOLVED, Ticket::STATUS_CLOSED, Ticket::STATUS_REJECTED])
        ->get();

    foreach ($tickets as $ticket) {
        $service->escalate($ticket);
    }

    $this->info("Escalated {$tickets->count()} overdue tickets.");
})->purpose('Escalate overdue tickets using the configured hierarchy');

Schedule::command('tickets:escalate')->daily();
