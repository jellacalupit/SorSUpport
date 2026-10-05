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
Schedule::command('notifications:send')->everyFiveMinutes();
