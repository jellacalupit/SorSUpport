<?php

use App\Services\EmailNotificationService;
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

// Escalation is a manual SDS admin decision, so nothing escalates or reminds on a schedule.
Schedule::command('notifications:send')->everyMinute()->withoutOverlapping();

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
