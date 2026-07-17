<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Complaint;
use Illuminate\Http\Request;
use Illuminate\View\View;



class AdminAnonymousComplaintController extends Controller
{
    /**
     * Display anonymous complaints informational list.
     */
    public function index(Request $request): View
    {
        $query = Complaint::query()
            ->where('is_anonymous', true)
            ->orderByDesc('created_at');

        if ($request->filled('search_reference')) {
            $reference = $request->input('search_reference');
            $query->where('reference_number', 'like', '%' . $reference . '%');
        }

        if ($request->filled('search_subject')) {
            $subject = $request->input('search_subject');
            $query->where('subject_title', 'like', '%' . $subject . '%');
        }

        if ($request->filled('status_filter')) {
            $query->where('status', $request->input('status_filter'));
        }

        $complaints = $query
            ->with(['category'])
            ->paginate(10)
            ->appends($request->query());

        return view('admin.complaints.anonymous_index', compact('complaints'));
    }

    /**
     * Show anonymous complaint details (hide student identity).
     */
    public function show(Complaint $complaint): View
    {
        abort_unless($complaint->is_anonymous === true, 404);

        $complaint->load(['category']);

        return view('admin.complaints.anonymous_show', compact('complaint'));
    }
}

