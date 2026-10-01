<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;

class StudentDashboardController extends Controller
{
    /**
     * Display the Student dashboard.
     */
    public function index()
    {
        $user = Auth::user();
        $student = $user->student;

        // Fetch all complaints for this student with category information
        $complaints = $student->complaints()
            ->with(['category', 'ticket.auditLogs'])
            ->latest()
            ->get();

        // Calculate counts by status
        $totalCount = $complaints->count();
        $statusOf = fn ($complaint) => $complaint->ticket?->status ?? $complaint->status;
        $pendingCount = $complaints->filter(fn($c) => $statusOf($c) === 'pending')->count();
        $inProgressCount = $complaints->filter(fn($c) => in_array($statusOf($c), ['in_progress', 'assigned']))->count();
        $escalatedCount = $complaints->filter(fn($c) => $statusOf($c) === 'escalated')->count();
        $resolvedCount = $complaints->filter(fn($c) => $statusOf($c) === 'resolved')->count();
        $closedCount = $complaints->filter(fn($c) => in_array($statusOf($c), ['rejected', 'closed']))->count();
        $recentTickets = $complaints
            ->sortByDesc(fn ($complaint) => $complaint->ticket?->updated_at?->timestamp ?? $complaint->created_at->timestamp)
            ->take(5);

        // Keep the user's given name readable without displaying the surname.
        $firstName = $user->given_name;
        $department = strtoupper(trim((string) ($student->department ?? '')));
        $department = str_contains($department, 'INFORMATION') || $department === 'CICT'
            ? 'CICT'
            : (str_contains($department, 'BUSINESS') || $department === 'CBME' ? 'CBME' : ($department !== '' ? $department : 'N/A'));
        $course = trim((string) ($student->course ?? ''));
        $courseLabel = preg_match('/^[A-Za-z]+$/', $course)
            ? strtoupper($course)
            : collect(preg_split('/\s+/', $course) ?: [])
                ->reject(fn ($word) => in_array(strtolower($word), ['of', 'in', 'and', 'the'], true))
                ->map(fn ($word) => preg_replace('/[^A-Za-z]/', '', $word))
                ->filter()
                ->map(fn ($word) => strtoupper(substr($word, 0, 1)))
                ->join('');
        $year = preg_replace('/\D/', '', (string) ($student->year_level ?? ''));
        $block = preg_replace('/\D/', '', (string) ($student->block ?? ''));
        $courseYearBlock = trim(($courseLabel !== '' ? $courseLabel : 'N/A') . ' ' . $year . ($block !== '' ? "-{$block}" : ''));

        return view('student.dashboard', [
            'firstName' => $firstName,
            'studentId' => $student->student_id ?: 'N/A',
            'department' => $department,
            'courseYearBlock' => $courseYearBlock,
            'totalCount' => $totalCount,
            'pendingCount' => $pendingCount,
            'inProgressCount' => $inProgressCount,
            'escalatedCount' => $escalatedCount,
            'resolvedCount' => $resolvedCount,
            'closedCount' => $closedCount,
            'recentTickets' => $recentTickets,
        ]);
    }
}