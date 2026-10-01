<?php

namespace Tests\Feature\Admin;

use App\Models\ComplaintCategory;
use App\Models\Recipient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryHierarchyTest extends TestCase
{
    use RefreshDatabase;

    public function test_category_recipients_and_multi_recipient_hierarchy_levels_are_saved_separately(): void
    {
        $admin = User::factory()->create([
            'role' => User::ROLE_SDS_ADMIN,
            'is_active' => true,
            'email_verified_at' => now(),
            'must_change_password' => false,
        ]);

        $recipients = collect(range(1, 4))->map(function (int $index): Recipient {
            $user = User::factory()->create([
                'role' => User::ROLE_RECIPIENT,
                'is_active' => true,
                'email_verified_at' => now(),
                'name' => "Recipient {$index}",
            ]);

            return Recipient::create([
                'user_id' => $user->id,
                'staff_id' => "R-{$index}",
                'department' => $index < 3 ? 'CICT' : 'CBME',
                'designation' => 'Instructor',
            ]);
        });

        $response = $this->actingAs($admin)->post(route('admin.categories.store'), [
            'name' => 'Academic Concern',
            'description' => 'Academic concern routing',
            'suggested_recipient_ids' => [$recipients[0]->id, $recipients[1]->id],
            'hierarchy_levels' => [
                ['level' => 1, 'recipient_ids' => [$recipients[2]->id, $recipients[3]->id]],
                ['level' => 2, 'recipient_ids' => [$recipients[0]->id]],
            ],
        ]);

        $response->assertRedirect(route('admin.settings'));

        $category = ComplaintCategory::query()->where('name', 'Academic Concern')->firstOrFail();
        $this->assertEqualsCanonicalizing(
            [$recipients[0]->id, $recipients[1]->id],
            $category->suggestedRecipients()->pluck('recipients.id')->all()
        );
        $this->assertDatabaseCount('escalation_hierarchies', 3);
        $this->assertDatabaseHas('escalation_hierarchies', [
            'complaint_category_id' => $category->id,
            'level' => 1,
            'recipient_id' => $recipients[2]->id,
        ]);
        $this->assertDatabaseHas('escalation_hierarchies', [
            'complaint_category_id' => $category->id,
            'level' => 1,
            'recipient_id' => $recipients[3]->id,
        ]);
        $this->assertDatabaseHas('escalation_hierarchies', [
            'complaint_category_id' => $category->id,
            'level' => 2,
            'recipient_id' => $recipients[0]->id,
        ]);
    }

    public function test_duplicate_level_definitions_are_rejected(): void
    {
        $admin = User::factory()->create([
            'role' => User::ROLE_SDS_ADMIN,
            'is_active' => true,
            'email_verified_at' => now(),
            'must_change_password' => false,
        ]);

        $recipientUser = User::factory()->create([
            'role' => User::ROLE_RECIPIENT,
            'is_active' => true,
            'email_verified_at' => now(),
        ]);
        $recipient = Recipient::create([
            'user_id' => $recipientUser->id,
            'staff_id' => 'R-DUP',
            'department' => 'CICT',
            'designation' => 'Instructor',
        ]);

        $response = $this->actingAs($admin)->from(route('admin.settings'))->post(route('admin.categories.store'), [
            'name' => 'Duplicate Levels',
            'suggested_recipient_ids' => [$recipient->id],
            'hierarchy_levels' => [
                ['level' => 1, 'recipient_ids' => [$recipient->id]],
                ['level' => 1, 'recipient_ids' => []],
            ],
        ]);

        $response->assertRedirect(route('admin.settings'))->assertSessionHasErrors('hierarchy_levels');
        $this->assertDatabaseMissing('complaint_categories', ['name' => 'Duplicate Levels']);
    }
}
