<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\ComplaintCategory;
use App\Models\EscalationHierarchy;
use App\Models\Recipient;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ComplaintCategoryController extends Controller
{
    /**
     * Display all complaint categories.
     */
    public function index(): View
    {
        $categories = ComplaintCategory::with('recipient.user')
            ->orderBy('name')
            ->paginate(10);

        return view('admin.categories.index', compact('categories'));
    }

    /**
     * Show create category form.
     */
    public function create(): RedirectResponse
    {
        return redirect()->route('admin.settings', ['add_category' => 1]);
    }

    /**
     * Store a new category.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'recipient_id' => 'nullable|exists:recipients,id',
            'resolution_deadline_days' => 'nullable|integer|min:1',
            'escalation_hierarchy' => 'nullable|string',
            'suggested_recipient_ids' => 'nullable|array',
            'suggested_recipient_ids.*' => 'integer|exists:recipients,id',
        ]);

        $category = DB::transaction(function () use ($validated): ComplaintCategory {
            $category = ComplaintCategory::create([
                'name' => $validated['name'],
                'description' => $validated['description'],
                'recipient_id' => $validated['recipient_id'],
                'resolution_deadline_days' => $validated['resolution_deadline_days'] ?? 1,
                'is_active' => true,
            ]);

            $suggestedRecipientIds = $validated['suggested_recipient_ids'] ?? [];
            $this->syncEscalationHierarchy($category, $validated['escalation_hierarchy'] ?? implode(', ', $suggestedRecipientIds));
            $category->suggestedRecipients()->sync($suggestedRecipientIds);

            return $category;
        });

        AuditLog::activity('category_created', details: sprintf('Created complaint category "%s".', $category->name));

        return redirect()
            ->route('admin.settings')
            ->with('success', 'Complaint category created successfully.');
    }

    /**
     * Show edit category form.
     */
    public function edit(ComplaintCategory $category): View
    {
        $recipients = Recipient::query()
            ->activeVerified()
            ->with('user')
            ->orderBy('department')
            ->get();

        return view('admin.categories.edit', compact(
            'category',
            'recipients'
        ));
    }

    /**
     * Update category.
     */
    public function update(Request $request, ComplaintCategory $category)
    {
        $validated = $request->validate([

            'name' => 'required|string|max:255',

            'description' => 'nullable|string',

            'recipient_id' => 'nullable|exists:recipients,id',

            'resolution_deadline_days' => 'nullable|integer|min:1',

            'escalation_hierarchy' => 'nullable|string',

            'suggested_recipient_ids' => 'nullable|array',

            'suggested_recipient_ids.*' => 'integer|exists:recipients,id',

        ]);

        DB::transaction(function () use ($category, $validated): void {
            $category->update([

                'name' => $validated['name'],

                'description' => $validated['description'],

                'recipient_id' => $validated['recipient_id'],

                ...array_key_exists('resolution_deadline_days', $validated) && $validated['resolution_deadline_days'] !== null
                    ? ['resolution_deadline_days' => $validated['resolution_deadline_days']]
                    : [],

            ]);

            $suggestedRecipientIds = $validated['suggested_recipient_ids'] ?? [];
            $hierarchyInput = empty($suggestedRecipientIds)
                ? ($validated['escalation_hierarchy'] ?? '')
                : implode(', ', $suggestedRecipientIds);
            $this->syncEscalationHierarchy($category, $hierarchyInput);
            $category->suggestedRecipients()->sync($suggestedRecipientIds);
        });

        AuditLog::activity('category_updated', details: sprintf('Updated complaint category "%s".', $category->name));

        return redirect()
            ->route('admin.settings')
            ->with('success', 'Complaint category updated successfully.');
    }

    /**
     * Deactivate category.
     */
    public function toggleStatus(ComplaintCategory $category)
    {
        $category->update([
            'is_active' => ! $category->is_active,
        ]);

        AuditLog::activity(
            $category->is_active ? 'category_activated' : 'category_deactivated',
            details: sprintf('%s complaint category "%s".', $category->is_active ? 'Activated' : 'Deactivated', $category->name)
        );

        return redirect()
            ->route('admin.categories.index')
            ->with(
                'success',
                $category->is_active
                    ? 'Complaint category activated successfully.'
                    : 'Complaint category deactivated successfully.'
            );
    }

    /**
     * Delete a complaint category and its related records.
     */
    public function destroy(ComplaintCategory $category)
    {
        $category->delete();

        AuditLog::activity('category_deleted', details: sprintf('Deleted complaint category "%s".', $category->name));

        return redirect()
            ->route('admin.settings')
            ->with('success', 'Complaint category deleted successfully.');
    }

    protected function syncEscalationHierarchy(ComplaintCategory $category, string $input): void
    {
        $category->escalationHierarchies()->delete();

        $recipientIds = array_values(array_filter(array_map('trim', preg_split('/[\s,]+/', $input) ?: [])));

        foreach ($recipientIds as $index => $recipientId) {
            if (! Recipient::whereKey($recipientId)->exists()) {
                continue;
            }

            EscalationHierarchy::create([
                'complaint_category_id' => $category->id,
                'level' => $index + 1,
                'recipient_id' => $recipientId,
            ]);
        }
    }
}