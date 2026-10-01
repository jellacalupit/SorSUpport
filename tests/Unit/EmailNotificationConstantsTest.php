<?php

namespace Tests\Unit;

use App\Models\EmailNotification;
use App\Models\User;
use App\Notifications\AccountUpdateNotification;
use App\Notifications\VerifyEmailNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class EmailNotificationConstantsTest extends TestCase
{
    #[Test]
    public function constants_are_defined_and_correct(): void
    {
        $this->assertSame('assignment', EmailNotification::TYPE_ASSIGNMENT);
        $this->assertSame('recipient_assignment', EmailNotification::TYPE_RECIPIENT_ASSIGNMENT);
        $this->assertSame('status_update', EmailNotification::TYPE_STATUS_UPDATE);
        $this->assertSame('student_status_update', EmailNotification::TYPE_STUDENT_STATUS_UPDATE);
        $this->assertSame('recipient_resolved', EmailNotification::TYPE_RECIPIENT_RESOLVED);
        $this->assertSame('complaint_closed', EmailNotification::TYPE_COMPLAINT_CLOSED);
        $this->assertSame('pending', EmailNotification::STATUS_PENDING);
        $this->assertSame('sent', EmailNotification::STATUS_SENT);
        $this->assertSame('failed', EmailNotification::STATUS_FAILED);
    }

    #[Test]
    public function verification_notification_is_sent_immediately_but_account_update_notification_stays_queueable(): void
    {
        $this->assertNotContains(ShouldQueue::class, class_implements(VerifyEmailNotification::class));
        $this->assertContains(ShouldQueue::class, class_implements(AccountUpdateNotification::class));
    }

    #[Test]
    public function blank_name_users_still_have_a_fallback_initial_for_the_avatar(): void
    {
        $user = new User([
            'first_name' => '',
            'middle_name' => '',
            'last_name' => '',
            'name' => '',
        ]);

        $this->assertSame('A', $user->name_initials);
    }
}
