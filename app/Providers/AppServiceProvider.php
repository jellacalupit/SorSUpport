<?php

namespace App\Providers;

use App\Services\EmailNotificationService;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\ServiceProvider;
use Symfony\Component\Mailer\Bridge\Brevo\Transport\BrevoTransportFactory;
use Symfony\Component\Mailer\Transport\Dsn;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(EmailNotificationService::class, fn () => new EmailNotificationService());
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Brevo delivers email over its web API, which works where a host blocks SMTP ports.
        Mail::extend('brevo', fn () => (new BrevoTransportFactory)->create(
            new Dsn('brevo+api', 'default', (string) config('services.brevo.key'))
        ));
    }
}
