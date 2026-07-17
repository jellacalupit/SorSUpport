<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ComplaintCategory;
use App\Models\EscalationHierarchy;
use App\Models\Recipient;
use Illuminate\Http\Request;
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
            ->get();

        return view('admin.categories.index', compact('categories'));
    }

    /**
     * Show create category form.
     */
    public function create(): View
    {
        $recipients = Recipient::with('user')
            ->orderBy('department')
            ->get();

        return view('admin.categories.create', compact('recipients'));
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
            'resolution_deadline_days' => 'required|integer|min:1',
            'escalation_hierarchy' => 'nullable|string',
        ]);

        $category = DB::transaction(function () use ($validated): ComplaintCategory {
            $category = ComplaintCategory::create([
                'name' => $validated['name'],
                'description' => $validated['description'],
                'recipient_id' => $validated['recipient_id'],
                'resolution_deadline_days' => $validated['resolution_deadline_days'],
                'is_active' => true,
            ]);

            $this->syncEscalationHierarchy($category, $validated['escalation_hierarchy'] ?? '');

            return $category;
        });

        return redirect()
            ->route('admin.categories.index')
            ->with('success', 'Complaint category created successfully.');
    }

    /**
     * Show edit category form.
     */
    public function edit(ComplaintCategory $category): View
    {
        $recipients = Recipient::with('user')
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

            'resolution_deadline_days' => 'required|integer|min:1',

            'escalation_hierarchy' => 'nullable|string',

        ]);

        DB::transaction(function () use ($category, $validated): void {
            $category->update([

                'name' => $validated['name'],

                'description' => $validated['description'],

                'recipient_id' => $validated['recipient_id'],

                'resolution_deadline_days' => $validated['resolution_deadline_days'],

            ]);

            $this->syncEscalationHierarchy($category, $validated['escalation_hierarchy'] ?? '');
        });

        return redirect()
            ->route('admin.categories.index')
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

        return redirect()
            ->route('admin.categories.index')
            ->with(
                'success',
                $category->is_active
                    ? 'Complaint category activated successfully.'
                    : 'Complaint category deactivated successfully.'
            );
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