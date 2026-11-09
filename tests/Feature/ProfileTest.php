<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\AuditLog;
use App\Models\Complaint;
use App\Models\ComplaintCategory;
use App\Models\Student;
use App\Models\ThreadMessage;
use App\Models\Ticket;
use App\Models\TicketThread;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_page_is_displayed(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->get('/profile');

        $response->assertOk();
    }

    public function test_profile_information_can_be_updated(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->patch('/profile', [
                'name' => 'Test User',
                'email' => 'test@example.com',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/profile');

        $user->refresh();

        $this->assertSame('Test User', $user->name);
        $this->assertSame('test@example.com', $user->email);
        $this->assertNull($user->email_verified_at);
    }

    public function test_sds_admin_can_edit_the_admin_profile_details(): void
    {
        $user = User::factory()->create([
            'role' => User::ROLE_SDS_ADMIN,
            'username' => '1001',
        ]);

        $this->actingAs($user)
            ->get('/profile')
            ->assertOk()
            ->assertSee('profile-username')
            ->assertSee('id="profile-photo-overlay"', false)
            ->assertSee('id="recipient-profile-photo"', false)
            ->assertSeeText('First Name *')
            ->assertSeeText('Last Name *')
            ->assertSeeText('Email *')
            ->assertSeeText('Staff ID *')
            ->assertSeeText('College / Office')
            ->assertSeeText('Position');

        // The office and designation must be ones configured in System Settings.
        \App\Models\Unit::create(['name' => 'Student Development Services', 'type' => \App\Models\Unit::TYPE_OFFICE])
            ->designations()
            ->create(['name' => 'Administrator']);

        $response = $this
            ->actingAs($user)
            ->patch('/profile', [
                'first_name' => 'Updated',
                'middle_name' => 'SDS',
                'last_name' => 'Admin',
                'extension' => 'Sr.',
                'email' => 'updated-admin@example.com',
                'username' => '1002',
                'unit' => 'Student Development Services',
                'designation' => 'Administrator',
                'role' => User::ROLE_SDS_ADMIN,
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/profile');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'Updated SDS Admin Sr.',
            'email' => 'updated-admin@example.com',
            'username' => '1002',
        ]);

        $this->assertDatabaseHas('recipients', [
            'user_id' => $user->id,
            'unit' => 'Student Development Services',
            'designation' => 'Administrator',
        ]);
    }

    public function test_sds_admin_cannot_change_their_own_role_from_the_profile_form(): void
    {
        $admin = User::factory()->create([
            'role' => User::ROLE_SDS_ADMIN,
            'username' => '1001',
        ]);

        $this->actingAs($admin)
            ->patch('/profile', [
                'first_name' => 'Still',
                'last_name' => 'Admin',
                'email' => 'still-admin@example.com',
                'username' => '1001',
                'role' => User::ROLE_STUDENT,
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(User::ROLE_SDS_ADMIN, $admin->fresh()->role);
    }

    public function test_sds_admin_profile_photo_control_starts_hidden_until_edit_mode(): void
    {
        $user = User::factory()->create([
            'role' => User::ROLE_SDS_ADMIN,
            'username' => '1001',
        ]);

        $this->actingAs($user)
            ->get('/profile')
            ->assertOk()
            ->assertSee('id="profile-photo-overlay"', false)
            ->assertSee('id="recipient-profile-photo"', false)
            ->assertSee('class="absolute bottom-1 right-1 z-20 hidden"', false)
            ->assertSee("getElementById('profile-photo-overlay')?.classList.remove('hidden')", false)
            ->assertSee("getElementById('profile-photo-overlay')?.classList.add('hidden')", false);
    }

    public function test_sds_admin_profile_does_not_infer_middle_name_from_full_name(): void
    {
        $user = User::factory()->create([
            'role' => User::ROLE_SDS_ADMIN,
            'name' => 'Mary Jane Smith',
            'first_name' => null,
            'middle_name' => null,
            'last_name' => null,
        ]);

        $this->actingAs($user)
            ->get('/profile')
            ->assertOk()
            ->assertSee('name="first_name" value="Mary Jane"', false)
            ->assertSee('name="middle_name" value=""', false)
            ->assertSee('name="last_name" value="Smith"', false);
    }

    public function test_email_verification_status_is_unchanged_when_the_email_address_is_unchanged(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->patch('/profile', [
                'name' => 'Test User',
                'email' => $user->email,
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/profile');

        $this->assertNotNull($user->refresh()->email_verified_at);
    }

    public function test_user_can_delete_their_account(): void
    {
        $user = User::factory()->create();
        $deletedUserName = $user->name;

        $response = $this
            ->actingAs($user)
            ->delete('/profile', [
                'password' => 'password',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/');

        $this->assertGuest();
        $this->assertNull($user->fresh());
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'account_deleted',
            'performed_by' => null,
            'details' => 'Deleted account ID ' . $user->id . ' with no related ticket records.',
        ]);
    }

    public function test_deleting_an_account_with_ticket_records_anonymizes_it_and_preserves_history(): void
    {
        Storage::fake('public');
        $user = User::factory()->create([
            'name' => 'Mary Privacy',
            'first_name' => 'Mary',
            'last_name' => 'Privacy',
            'username' => 'mary.privacy',
            'email' => 'mary.privacy@example.test',
            'avatar_path' => 'avatars/mary.png',
        ]);
        Storage::disk('public')->put('avatars/mary.png', 'avatar');
        $student = Student::create([
            'user_id' => $user->id,
            'student_id' => '20249981',
            'college' => 'CICT',
            'program' => 'BSIT',
            'year_level' => '1',
            'block' => '1',
        ]);
        $category = ComplaintCategory::create(['name' => 'General', 'is_active' => true]);
        $complaint = Complaint::create([
            'reference_number' => Complaint::generateReferenceNumber(),
            'student_id' => $student->id,
            'category_id' => $category->id,
            'subject_title' => 'Retained concern',
            'description' => 'Retained complaint details.',
        ]);
        $ticket = Ticket::create([
            'complaint_id' => $complaint->id,
            'status' => Ticket::STATUS_SUBMITTED,
        ]);
        $thread = TicketThread::create(['ticket_id' => $ticket->id, 'is_active' => true]);
        $message = ThreadMessage::create([
            'thread_id' => $thread->id,
            'sender_id' => $user->id,
            'content' => 'A retained message.',
        ]);
        $priorAudit = AuditLog::create([
            'ticket_id' => $ticket->id,
            'performed_by' => $user->id,
            'action' => 'message_posted',
            'details' => 'Mary Privacy posted a reply message.',
        ]);
        $id = $user->id;

        $this->actingAs($user)
            ->delete('/profile', ['password' => 'password'])
            ->assertSessionHasNoErrors()
            ->assertRedirect('/');

        $anonymized = User::findOrFail($id);
        $this->assertGuest();
        $this->assertSame('Deleted user', $anonymized->name);
        $this->assertSame('Deleted user', $anonymized->display_name);
        $this->assertSame("deleted-{$id}", $anonymized->username);
        $this->assertSame("deleted-{$id}@deleted.invalid", $anonymized->email);
        $this->assertFalse($anonymized->is_active);
        $this->assertNull($anonymized->avatar_path);
        Storage::disk('public')->assertMissing('avatars/mary.png');
        $this->assertDatabaseHas('complaints', ['id' => $complaint->id]);
        $this->assertDatabaseHas('tickets', ['id' => $ticket->id]);
        $this->assertDatabaseHas('thread_messages', [
            'id' => $message->id,
            'sender_id' => $id,
            'content' => 'A retained message.',
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'id' => $priorAudit->id,
            'performed_by' => $id,
            'details' => 'Deleted user posted a reply message.',
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'account_anonymized',
            'performed_by' => $id,
        ]);
        $this->assertDatabaseMissing('users', ['username' => 'mary.privacy']);
        $this->assertDatabaseMissing('users', ['email' => 'mary.privacy@example.test']);
        $replacement = User::factory()->create([
            'username' => 'mary.privacy',
            'email' => 'mary.privacy@example.test',
        ]);
        $this->assertNotNull($replacement->id);
    }

    public function test_correct_password_must_be_provided_to_delete_account(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->from('/profile')
            ->delete('/profile', [
                'password' => 'wrong-password',
            ]);

        $response
            ->assertSessionHasErrorsIn('userDeletion', 'password')
            ->assertRedirect('/profile');

        $this->assertNotNull($user->fresh());
    }
}
