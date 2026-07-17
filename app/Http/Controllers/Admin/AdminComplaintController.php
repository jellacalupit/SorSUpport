<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Complaint;
use Illuminate\Http\Request;

use Illuminate\View\View;

class AdminComplaintController extends Controller
{
    /**
     * Display identified complaints (non-anonymous) queue.
     */
    public function index(Request $request): View
    {
        $query = Complaint::query()
            ->where('is_anonymous', false)
            ->with([
                'category.recipient.user',
                'student.user',
                'ticket',
                'ticket.assignee',
                'ticket.thread',
            ])
            ->orderByDesc('created_at');

        if ($request->filled('search_reference')) {
            $reference = $request->input('search_reference');
            $query->where('reference_number', 'like', '%' . $reference . '%');
        }

        if ($request->filled('search_student')) {
            $student = $request->input('search_student');
            $query->whereHas('student.user', function ($q) use ($student) {
                $q->where('name', 'like', '%' . $student . '%');
            });
        }

        if ($request->filled('search_subject')) {
            $subject = $request->input('search_subject');
            $query->where('subject_title', 'like', '%' . $subject . '%');
        }

        if ($request->filled('status_filter')) {
            $query->where('status', $request->input('status_filter'));
        }

        $complaints = $query->paginate(10)->appends($request->query());

        return view('admin.complaints.index', compact('complaints'));
    }

    /**
     * Show identified complaint details.
     */
    public function show(Complaint $complaint): View
    {
        abort_unless($complaint->is_anonymous === false, 404);

        $complaint->load([
            'student.user',
            'category.recipient.user',
            'ticket.assignee',
            'ticket.thread.messages.sender',
            'ticket.auditLogs.performer',
        ]);

        // Identified complaints should have tickets; if not, still show what we have.
        return view('admin.complaints.show', compact('complaint'));
    }
}

