<?php

namespace Tests\Unit;

use App\Models\EmailNotification;
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
}
