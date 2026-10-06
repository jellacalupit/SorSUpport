<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\ComplaintCategory;
use App\Models\EscalationHierarchy;
use App\Models\Recipient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ComplaintCategoryController extends Controller
{
    public function index(): View
    {
        $categories = ComplaintCategory::with('recipient.user')->orderBy('name')->paginate(10);

        return view('admin.categories.index', compact('categories'));
    }

    public function create(): RedirectResponse
    {
        return redirect()->route('admin.settings', ['add_category' => 1]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validatedCategory($request);

        $category = DB::transaction(function () use ($validated): ComplaintCategory {
            $category = ComplaintCategory::create([
                'name' => $validated['name'],
                'description' => $validated['description'] ?? null,
                'recipient_id' => $validated['recipient_id'] ?? null,
                'resolution_deadline_days' => $validated['resolution_deadline_days'] ?? 1,
                'is_active' => true,
            ]);

            $category->suggestedRecipients()->sync($validated['suggested_recipient_ids'] ?? []);
            $this->syncEscalationHierarchy($category, $validated['hierarchy_levels'] ?? []);

            return $category;
        });

        AuditLog::activity('category_created', details: sprintf('Created complaint category "%s".', $category->name));

        return redirect()->route('admin.settings')->with('success', 'Complaint category created successfully.');
    }

    public function edit(ComplaintCategory $category): View
    {
        $recipients = Recipient::query()->activeVerified()->with('user')->orderBy('department')->get();

        return view('admin.categories.edit', compact('category', 'recipients'));
    }

    public function update(Request $request, ComplaintCategory $category): RedirectResponse
    {
        $validated = $this->validatedCategory($request);

        DB::transaction(function () use ($category, $validated): void {
            $category->update([
                'name' => $validated['name'],
                'description' => $validated['description'] ?? null,
                'recipient_id' => $validated['recipient_id'] ?? null,
                ...array_key_exists('resolution_deadline_days', $validated) && $validated['resolution_deadline_days'] !== null
                    ? ['resolution_deadline_days' => $validated['resolution_deadline_days']]
                    : [],
            ]);

            $category->suggestedRecipients()->sync($validated['suggested_recipient_ids'] ?? []);

            // The category form does not carry the hierarchy; only touch it when it was sent.
            if (array_key_exists('hierarchy_levels', $validated)) {
                $this->syncEscalationHierarchy($category, $validated['hierarchy_levels'] ?? []);
            }
        });

        AuditLog::activity('category_updated', details: sprintf('Updated complaint category "%s".', $category->name));

        return redirect()->route('admin.settings')->with('success', 'Complaint category updated successfully.');
    }

    /**
     * Save the escalation paths of a category. Each path is an ordered list of recipients;
     * the position in the list is the escalation level.
     */
    public function updateEscalation(Request $request, ComplaintCategory $category): RedirectResponse
    {
        $validated = $request->validate([
            'paths' => 'nullable|array',
            'paths.*.name' => 'nullable|string|max:100',
            'paths.*.recipient_ids' => 'nullable|array',
            'paths.*.recipient_ids.*' => 'integer|exists:recipients,id',
        ]);

        $paths = collect($validated['paths'] ?? [])
            ->map(fn (array $path): array => [
                'name' => trim((string) ($path['name'] ?? '')),
                'recipient_ids' => array_values(array_unique(array_map('intval', $path['recipient_ids'] ?? []))),
            ])
            ->filter(fn (array $path): bool => $path['recipient_ids'] !== [])
            ->values();

        $recipientIds = $paths->flatMap(fn (array $path): array => $path['recipient_ids'])->unique();

        if ($recipientIds->isNotEmpty() && Recipient::query()->active()->whereIn('id', $recipientIds)->count() !== $recipientIds->count()) {
            throw ValidationException::withMessages(['paths' => 'Only active recipients can be placed in an escalation path.']);
        }

        DB::transaction(function () use ($category, $paths): void {
            $category->escalationHierarchies()->delete();

            foreach ($paths as $pathIndex => $path) {
                foreach ($path['recipient_ids'] as $levelIndex => $recipientId) {
                    EscalationHierarchy::create([
                        'complaint_category_id' => $category->id,
                        'path_number' => $pathIndex + 1,
                        'path_name' => $path['name'] !== '' ? $path['name'] : null,
                        'level' => $levelIndex + 1,
                        'recipient_id' => $recipientId,
                    ]);
                }
            }
        });

        AuditLog::activity('escalation_hierarchy_updated', details: sprintf('Updated the escalation hierarchy of "%s".', $category->name));

        return redirect()
            ->route('admin.settings', ['settings_tab' => 'escalation', 'category' => $category->id])
            ->with('success', 'Escalation hierarchy saved.');
    }

    public function toggleStatus(ComplaintCategory $category): RedirectResponse
    {
        $category->update(['is_active' => ! $category->is_active]);

        AuditLog::activity(
            $category->is_active ? 'category_activated' : 'category_deactivated',
            details: sprintf('%s complaint category "%s".', $category->is_active ? 'Activated' : 'Deactivated', $category->name)
        );

        return redirect()->route('admin.categories.index')->with(
            'success',
            $category->is_active ? 'Complaint category activated successfully.' : 'Complaint category deactivated successfully.'
        );
    }

    public function destroy(ComplaintCategory $category): RedirectResponse
    {
        $category->delete();

        AuditLog::activity('category_deleted', details: sprintf('Deleted complaint category "%s".', $category->name));

        return redirect()->route('admin.settings')->with('success', 'Complaint category deleted successfully.');
    }

    protected function validatedCategory(Request $request): array
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'recipient_id' => 'nullable|exists:recipients,id',
            'resolution_deadline_days' => 'nullable|integer|min:1',
            'suggested_recipient_ids' => 'nullable|array',
            'suggested_recipient_ids.*' => 'integer|distinct|exists:recipients,id',
            'hierarchy_levels' => 'nullable|array',
            'hierarchy_levels.*.level' => 'required|integer|min:1',
            'hierarchy_levels.*.recipient_ids' => 'nullable|array',
            'hierarchy_levels.*.recipient_ids.*' => 'integer|distinct|exists:recipients,id',
        ]);

        $levels = $validated['hierarchy_levels'] ?? [];
        $levelNumbers = array_map(fn (array $level): int => (int) $level['level'], $levels);

        if (count($levelNumbers) !== count(array_unique($levelNumbers))) {
            throw ValidationException::withMessages([
                'hierarchy_levels' => 'Each escalation level may only be configured once per category.',
            ]);
        }

        $recipientIds = collect($validated['suggested_recipient_ids'] ?? [])
            ->merge(collect($levels)->flatMap(fn (array $level): array => $level['recipient_ids'] ?? []))
            ->unique()
            ->values();

        if ($recipientIds->isNotEmpty()) {
            $activeRecipientCount = Recipient::query()
                ->active()
                ->whereIn('id', $recipientIds)
                ->count();

            if ($activeRecipientCount !== $recipientIds->count()) {
                throw ValidationException::withMessages([
                    'suggested_recipient_ids' => 'Only active recipients can be selected.',
                ]);
            }
        }

        return $validated;
    }

    protected function syncEscalationHierarchy(ComplaintCategory $category, array $levels): void
    {
        $category->escalationHierarchies()->delete();

        foreach ($levels as $level) {
            foreach ($level['recipient_ids'] ?? [] as $recipientId) {
                EscalationHierarchy::create([
                    'complaint_category_id' => $category->id,
                    'level' => (int) $level['level'],
                    'recipient_id' => (int) $recipientId,
                ]);
            }
        }
    }
}
