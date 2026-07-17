<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ComplaintCategory;
use App\Models\Recipient;
use Illuminate\Http\Request;
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
        ]);
        ComplaintCategory::create([
            'name' => $validated['name'],
            'description' => $validated['description'],
            'recipient_id' => $validated['recipient_id'],
            'resolution_deadline_days' => $validated['resolution_deadline_days'],
            'is_active' => true,
        ]);

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

        ]);

        $category->update([

            'name' => $validated['name'],

            'description' => $validated['description'],

            'recipient_id' => $validated['recipient_id'],

            'resolution_deadline_days' => $validated['resolution_deadline_days'],

        ]);

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
}