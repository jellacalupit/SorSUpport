<?php

use App\Models\Ticket;
use App\Services\EmailNotificationService;
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

Artisan::command('notifications:send', function (EmailNotificationService $service) {
    $count = $service->sendPendingNotifications();

    $this->info("Sent {$count} pending email notifications.");
})->purpose('Send all pending email notifications');

Artisan::command('notifications:remind', function (EmailNotificationService $service) {
    $count = $service->sendDailyReminders();

    $this->info("Sent {$count} daily reminder notifications.");
})->purpose('Send daily reminder emails for tickets nearing their deadline');

Schedule::command('tickets:escalate')->daily();
Schedule::command('notifications:send')->everyFiveMinutes();
Schedule::command('notifications:remind')->daily();
