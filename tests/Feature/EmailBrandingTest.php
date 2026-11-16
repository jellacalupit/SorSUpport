<?php

namespace Tests\Feature;

use Illuminate\Mail\Message;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class EmailBrandingTest extends TestCase
{
    public function test_email_logos_use_the_public_site_even_when_sent_from_localhost(): void
    {
        $this->assertSame(
            'https://sorsupport.up.railway.app/branding/sorsu%20logo.png',
            config('mail.logo_url'),
        );

        $this->assertLogoInOutgoingMessages(config('mail.logo_url'));
    }

    public function test_a_configured_logo_url_is_used_by_every_email_template(): void
    {
        config(['mail.logo_url' => 'https://images.example.com/support.png']);

        $this->assertLogoInOutgoingMessages('https://images.example.com/support.png');
    }

    private function assertLogoInOutgoingMessages(string $expectedUrl): void
    {
        config(['app.url' => 'http://localhost:8000']);
        URL::forceRootUrl('http://localhost:8000');

        $views = [
            'emails.verify-email' => ['verificationUrl' => 'https://example.com/verify'],
            'emails.password-reset' => ['resetUrl' => 'https://example.com/reset', 'expires' => 15],
            'emails.notifications.layout' => ['subject' => 'Ticket update', 'lines' => ['Your ticket was updated.']],
            'emails.account-update' => ['subject' => 'Account update', 'email_message' => 'Your account was updated.'],
        ];

        foreach ($views as $view => $data) {
            // Build the actual outgoing message, but use an in-memory transport: no email is sent.
            $sent = Mail::mailer('array')->send($view, $data, function (Message $message): void {
                $message->from('support@example.com', 'SorSUpport')
                    ->to('student@example.com')
                    ->subject('Logo regression test');
            });

            $html = $sent->getOriginalMessage()->getHtmlBody();
            $this->assertStringContainsString('src="'.$expectedUrl.'"', $html, $view);
            $this->assertStringNotContainsString('src="http://localhost', $html, $view);
            $this->assertStringContainsString('alt="SorSUpport"', $html, $view);
        }
    }
}
