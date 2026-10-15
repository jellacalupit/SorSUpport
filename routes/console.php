<?php

use App\Services\EmailNotificationService;
use App\Services\TicketReminderService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('notifications:send', function (EmailNotificationService $service) {
    $count = $service->sendPendingNotifications();

    $this->info("Sent {$count} pending email notifications.");
})->purpose('Send all pending email notifications');

Artisan::command('tickets:send-reminders', function (TicketReminderService $service) {
    $count = $service->queueReminders();

    $this->info("Queued reminders for {$count} waiting tickets.");
})->purpose('Email the people holding tickets that have waited too long');

// Escalation stays a manual SDS admin decision; the schedule only sends emails and reminders.
// Reminders are checked every hour of the working day, so a ticket allowed one working day is
// reminded about soon after it has waited that long, and never on a weekend.
Schedule::command('notifications:send')->everyMinute()->withoutOverlapping();
Schedule::command('tickets:send-reminders')->hourly()->weekdays()->between('8:00', '17:00')->withoutOverlapping();

// Connects and signs in to the mail server without sending anything, so a deployment can
// tell at once whether email will go out.
Artisan::command('mail:check', function () {
    try {
        if (config('mail.default') === 'brevo') {
            $response = \Illuminate\Support\Facades\Http::timeout(20)
                ->withHeaders(['api-key' => (string) config('services.brevo.key'), 'accept' => 'application/json'])
                ->get('https://api.brevo.com/v3/account');

            if ($response->failed()) {
                throw new \RuntimeException('Brevo refused the request: ' . ($response->json('message') ?? $response->status()));
            }

            $this->info('MAIL CHECK: Brevo accepted the API key.');

            return 0;
        }

        $transport = \Illuminate\Support\Facades\Mail::mailer()->getSymfonyTransport();

        if (method_exists($transport, 'start')) {
            $transport->start();
            $transport->stop();
        }

        $this->info('MAIL CHECK: the mail server accepted the connection and sign-in.');

        return 0;
    } catch (\Throwable $e) {
        $this->error('MAIL CHECK FAILED: ' . $e->getMessage());

        return 1;
    }
})->purpose('Check that the configured mail server can be reached');
