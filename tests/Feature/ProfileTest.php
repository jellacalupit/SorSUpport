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

    public function test_no_role_can_delete_their_account_through_the_profile_endpoint(): void
    {
        foreach ([User::ROLE_STUDENT, User::ROLE_RECIPIENT, User::ROLE_SDS_ADMIN] as $role) {
            $user = User::factory()->create(['role' => $role]);
            $attributes = $user->fresh()->getAttributes();

            $this->actingAs($user)
                ->delete('/profile', ['password' => 'password'])
                ->assertStatus(405);

            $this->assertSame($attributes, $user->fresh()->getAttributes());
        }

        $this->assertDatabaseMissing('audit_logs', ['action' => 'account_deleted']);
        $this->assertDatabaseMissing('audit_logs', ['action' => 'account_anonymized']);
    }

    public function test_attempted_account_deletion_preserves_identity_avatar_and_ticket_history(): void
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
            ->assertStatus(405);

        $retained = User::findOrFail($id);
        $this->assertAuthenticatedAs($retained);
        $this->assertSame('Mary Privacy', $retained->name);
        $this->assertSame('Mary Privacy', $retained->display_name);
        $this->assertSame('mary.privacy', $retained->username);
        $this->assertSame('mary.privacy@example.test', $retained->email);
        $this->assertTrue($retained->is_active);
        $this->assertSame('avatars/mary.png', $retained->avatar_path);
        Storage::disk('public')->assertExists('avatars/mary.png');
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
            'details' => 'Mary Privacy posted a reply message.',
        ]);
        $this->assertDatabaseMissing('audit_logs', ['action' => 'account_anonymized']);
    }

    public function test_user_model_rejects_account_deletion(): void
    {
        $user = User::factory()->create();

        try {
            $user->delete();
            $this->fail('Account deletion should be rejected.');
        } catch (\LogicException $exception) {
            $this->assertSame('Accounts cannot be deleted. Deactivate the account instead.', $exception->getMessage());
        }

        $this->assertNotNull($user->fresh());
    }
}
