<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Complaint;
use App\Models\ComplaintCategory;
use App\Models\ThreadMessage;
use App\Models\Ticket;
use App\Models\TicketThread;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use App\Models\EmailNotification;

use Illuminate\Validation\Rule;
use Illuminate\View\View;


class ComplaintController extends Controller
{
    /**
     * Display all complaints submitted by the authenticated student.
     */
    public function index(): View
    {
        $student = Auth::user()->student;

        if (! $student) {
            abort(403, 'Student profile not found.');
        }

        $complaints = Complaint::query()
            ->where('student_id', $student->id)
            ->with(['category.recipient.user'])
            ->orderByDesc('created_at')
            ->paginate(10);

        return view('student.complaints.index', compact('complaints'));
    }

    /**
     * Show the complaint submission form.
     */
    public function create(): View
    {
        $categories = ComplaintCategory::query()
            ->where('is_active', true)
            ->whereNotNull('recipient_id')
            ->orderBy('name')
            ->get();

        return view('student.complaints.create', compact('categories'));
    }

    /**
     * Store a newly submitted complaint.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'category_id' => [
                'required',
                Rule::exists('complaint_categories', 'id')
                    ->where('is_active', true)
                    ->whereNotNull('recipient_id'),
            ],
            'subject_title' => 'required|string|max:255',
            'personnel_involved' => 'nullable|string|max:255',
            'description' => 'required|string|max:5000',
            'file_attachment' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
            'is_anonymous' => 'nullable|boolean',
        ]);

        $student = Auth::user()->student;

        if (! $student) {
            abort(403, 'Student profile not found.');
        }

        $category = ComplaintCategory::with('recipient.user')
            ->findOrFail($validated['category_id']);

        $recipientUserId = $category->recipient?->user_id;

        if (! $recipientUserId) {
            return back()
                ->withInput()
                ->withErrors([
                    'category_id' => 'The selected category has no assigned recipient.',
                ]);
        }

        $attachmentPath = null;

        if ($request->hasFile('file_attachment')) {
            $attachmentPath = $request->file('file_attachment')
                ->store('complaints', 'public');
        }

        $isAnonymous = (bool) ($validated['is_anonymous'] ?? false);

        $complaint = DB::transaction(function () use (
            $validated,
            $student,
            $category,
            $recipientUserId,
            $attachmentPath,
            $isAnonymous
        ) {
            $complaint = Complaint::create([
                'reference_number' => Complaint::generateReferenceNumber(),
                'student_id' => $student->id,
                'category_id' => $category->id,
                'subject_title' => $validated['subject_title'],
                'personnel_involved' => $validated['personnel_involved'] ?? null,
                'description' => $validated['description'],
                'file_attachment' => $attachmentPath,
                'is_anonymous' => $isAnonymous,
                'status' => Complaint::STATUS_PENDING,
            ]);

            // Anonymous submissions should not generate tickets/threads and should not notify SDS Admin.
            if ($isAnonymous) {
                return $complaint;
            }

            $ticket = Ticket::create([

                'complaint_id' => $complaint->id,
                'assigned_to' => $recipientUserId,
                'status' => Ticket::STATUS_PENDING,
                'deadline' => now()->addDays($category->resolution_deadline_days),
            ]);

            $thread = TicketThread::create([
                'ticket_id' => $ticket->id,
                'is_active' => true,
            ]);

            ThreadMessage::create([
                'thread_id' => $thread->id,
                'sender_id' => Auth::id(),
                'content' => $validated['description'],
                'file_attachment' => $attachmentPath,
            ]);

            AuditLog::create([
                'ticket_id' => $ticket->id,
                'performed_by' => Auth::id(),
                'action' => 'complaint_submitted',
                'details' => "Complaint {$complaint->reference_number} submitted.",
            ]);

            // In-app notification record for SDS Admin (no real email sending yet)
            $sdsAdminEmail = optional(Auth::user())->email; // fallback; we resolve below from an SDS admin user
            $sdsAdminUser = \App\Models\User::query()->whereHas('roles', function ($q) {
                $q->where('name', 'sds_admin');
            })->first();

            if ($sdsAdminUser) {
                $sdsAdminEmail = $sdsAdminUser->email;
            }

            if ($sdsAdminEmail) {
                EmailNotification::create([
                    'ticket_id' => $ticket->id,
                    'recipient_email' => $sdsAdminEmail,
                    // use existing enum value; this will be updated when real mail/events are enabled
                    'type' => 'assignment',
                    'status' => 'pending',
                ]);

            }

            return $complaint;

        });

        return redirect()
            ->route('student.complaints.show', $complaint)
            ->with('success', 'Your complaint has been submitted successfully.');
    }

    /**
     * Display complaint details.
     */
    public function show(Complaint $complaint): View
    {
        $student = Auth::user()->student;

        if (! $student || $complaint->student_id !== $student->id) {
            abort(403);
        }

        $complaint->load([
            'category.recipient.user',
            'ticket.assignee',
            'ticket.thread' => function ($q) {
                $q->with(['messages' => function ($q2) {
                    $q2->with('sender')->orderBy('created_at');
                }]);
            },
            'ticket.auditLogs' => function ($q) {
                $q->with('performer')->orderBy('created_at');
            },
        ]);

        return view('student.complaints.show', compact('complaint'));
    }
}
