<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Imports\AccountsImport;
use App\Models\Recipient;
use App\Models\Department;
use App\Models\Student;
use App\Models\AuditLog;
use App\Models\User as AppUser;
use App\Notifications\AccountUpdateNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;

class AccountManagementController extends Controller
{
    protected function normalizeMiddleName(mixed $middleName): string
    {
        $middleName = trim((string) $middleName);
        $middleName = rtrim($middleName, '.');

        if ($middleName === '') {
            return '';
        }

        return preg_match('/^[A-Za-z]$/', $middleName) === 1 ? '' : $middleName;
    }

    protected function buildFullName(Request $request, ?string $fallback = null): string
    {
        $firstName = trim((string) $request->input('first_name', ''));
        $middleName = $this->normalizeMiddleName($request->input('middle_name', ''));
        $lastName = trim((string) $request->input('last_name', ''));

        $candidate = implode(' ', array_filter([$firstName, $middleName, $lastName], fn ($value) => $value !== ''));

        return $candidate !== '' ? $candidate : trim((string) ($fallback ?? $request->input('name', '')));
    }

    /**
     * Display all accounts.
     */
    public function index(Request $request): View
    {
        $applyFilters = function ($query) use ($request) {
            if ($request->filled('search')) {
                $search = trim($request->input('search'));

                $query->where(function ($query) use ($search) {
                    $query->where('name', 'like', '%' . $search . '%')
                        ->orWhere('email', 'like', '%' . $search . '%')
                        ->orWhereHas('student', fn ($studentQuery) => $studentQuery->where('student_id', 'like', '%' . $search . '%'))
                        ->orWhereHas('recipient', fn ($recipientQuery) => $recipientQuery->where('staff_id', 'like', '%' . $search . '%'));
                });
            }

            if ($request->filled('status_filter')) {
                $query->where('is_active', $request->input('status_filter') === 'active');
            }

            if ($request->filled('department_filter')) {
                $department = $request->input('department_filter');

                if ($request->input('category_filter', 'students') === 'recipients') {
                    $query->whereHas('recipient', fn ($recipientQuery) => $recipientQuery->where('department', $department));
                } else {
                    $query->whereHas('student', fn ($studentQuery) => $studentQuery->where('department', $department));
                }
            }

            if ($request->filled('course_filter')) {
                $query->whereHas('student', fn ($studentQuery) => $studentQuery->where('course', $request->input('course_filter')));
            }

            if ($request->filled('year_filter')) {
                $query->whereHas('student', fn ($studentQuery) => $studentQuery->where('year_level', $request->input('year_filter')));
            }

            if ($request->filled('block_filter')) {
                $query->whereHas('student', fn ($studentQuery) => $studentQuery->where('block', $request->input('block_filter')));
            }

            return $query;
        };

        $studentUsers = $applyFilters(AppUser::with(['student', 'recipient'])
            ->where('role', AppUser::ROLE_STUDENT));

        $direction = $request->input('sort_id', 'asc') === 'asc' ? 'asc' : 'desc';
        $studentUsers = $studentUsers
            ->leftJoin('students', 'students.user_id', '=', 'users.id')
            ->orderByRaw("CAST(students.student_id AS INTEGER) {$direction}")
            ->orderBy('users.id', $direction)
            ->select('users.*');

        $studentUsers = $studentUsers->paginate(15, ['*'], 'students_page')->appends($request->query());

        $recipientUsers = $applyFilters(AppUser::with(['student', 'recipient'])
            ->whereIn('role', [AppUser::ROLE_RECIPIENT, AppUser::ROLE_SDS_ADMIN]));

        $recipientUsers = $recipientUsers
            ->leftJoin('recipients', 'recipients.user_id', '=', 'users.id')
            ->orderBy('recipients.staff_id', $direction)
            ->orderBy('users.id', $direction)
            ->select('users.*');

        $recipientUsers = $recipientUsers
            ->paginate(15, ['*'], 'recipients_page')
            ->appends($request->query());

        $departments = Department::query()
            ->with(['positions', 'courses'])
            ->orderBy('name')
            ->get();
        $studentDepartments = $departments->where('type', 'student')->values();
        $recipientDepartments = $departments->where('type', 'recipient')->values();

        return view('admin.accounts.index', compact('studentUsers', 'recipientUsers', 'departments', 'studentDepartments', 'recipientDepartments'));
    }

    /**
     * Store a new account.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'middle_name' => 'nullable|string|max:255',
            'email' => 'required|email|unique:users,email',
            'role' => 'required|in:student,recipient',
        ]);

        $fullName = $this->buildFullName($request);
        $username = null;

        if ($validated['role'] === AppUser::ROLE_STUDENT) {
            $studentData = $request->validate([
                'student_id' => ['required', 'regex:/^\d{8}$/'],
                'department' => 'required|string|max:255',
                'course' => 'required|string|max:255',
                'year_level' => 'required|integer|between:1,4',
                'block' => 'nullable|integer|min:1',
            ]);
            $username = $request->student_id;
        }

        if ($validated['role'] === AppUser::ROLE_RECIPIENT) {
            $recipientData = $request->validate([
                'staff_id' => ['required', 'string', 'max:255'],
                'recipient_department' => ['required', 'string', Rule::exists('departments', 'name')->where(fn ($query) => $query->where('type', 'recipient'))],
                'designation' => ['required', 'string', 'max:255'],
            ]);
            $username = $request->staff_id;
        }

        $defaultPassword = $username ?? $validated['email'];

        $user = AppUser::create([
            'name' => $fullName,
            'first_name' => $request->input('first_name'),
            'middle_name' => $request->input('middle_name'),
            'last_name' => $request->input('last_name'),
            'username' => $username,
            'email' => $validated['email'],
            'password' => Hash::make($defaultPassword),
            'must_change_password' => true,
            'role' => $validated['role'],
            'is_active' => false,
            'email_verified_at' => null,
        ]);

        if ($validated['role'] === AppUser::ROLE_STUDENT) {

            Student::create([
                'user_id' => $user->id,
                'student_id' => $studentData['student_id'],
                'department' => $studentData['department'],
                'course' => $studentData['course'],
                'year_level' => $studentData['year_level'],
                'block' => $studentData['block'] ?? null,
            ]);

        }

        if ($validated['role'] === AppUser::ROLE_RECIPIENT) {

            Recipient::create([
                'user_id' => $user->id,
                'staff_id' => $recipientData['staff_id'],
                'department' => $recipientData['recipient_department'],
                'designation' => $recipientData['designation'],
            ]);

        }

        $category = $validated['role'] === AppUser::ROLE_RECIPIENT ? 'recipients' : 'students';

        AuditLog::activity(
            'account_created',
            details: sprintf('Created %s account for %s.', $validated['role'], $user->name)
        );

        return redirect()
            ->route('admin.accounts.index', ['category_filter' => $category]);
    }

    /**
     * Update an account.
     */
    public function update(Request $request, AppUser $user)
    {
        $validated = $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'middle_name' => 'nullable|string|max:255',
            'email' => 'required|email|unique:users,email,' . $user->id,
        ]);

        $fullName = $this->buildFullName($request, $user->name);

        $user->update([
            'name' => $fullName,
            'first_name' => $request->input('first_name'),
            'middle_name' => $request->input('middle_name'),
            'last_name' => $request->input('last_name'),
            'email' => $validated['email'],
        ]);

        if ($user->role === AppUser::ROLE_STUDENT) {
            $studentData = $request->validate([
                'student_id' => ['required', 'regex:/^\d{8}$/'],
                'department' => 'required|string|max:255',
                'course' => 'required|string|max:255',
                'year_level' => 'required|integer|between:1,4',
                'block' => 'nullable|integer|min:1',
            ]);

            $user->update([
                'username' => $studentData['student_id'],
            ]);

            if ($user->student) {
                $user->student->update([
                    'student_id' => $studentData['student_id'],
                    'department' => $studentData['department'],
                    'course' => $studentData['course'],
                    'year_level' => $studentData['year_level'],
                    'block' => $studentData['block'] ?? null,
                ]);
            } else {
                $user->student()->create([
                    'student_id' => $studentData['student_id'],
                    'department' => $studentData['department'],
                    'course' => $studentData['course'],
                    'year_level' => $studentData['year_level'],
                    'block' => $studentData['block'] ?? null,
                ]);
            }
        }

        if ($user->role === AppUser::ROLE_RECIPIENT) {
            $recipientData = $request->validate([
                'staff_id' => ['required', 'string', 'max:255'],
                'recipient_department' => ['required', 'string', Rule::exists('departments', 'name')->where(fn ($query) => $query->where('type', 'recipient'))],
                'designation' => ['required', 'string', 'max:255'],
            ]);
            $user->update([
                'username' => $recipientData['staff_id'],
            ]);

            if ($user->recipient) {
                $user->recipient->update([
                    'staff_id' => $recipientData['staff_id'],
                    'department' => $recipientData['recipient_department'],
                    'designation' => $recipientData['designation'],
                ]);
            } else {
                $user->recipient()->create([
                    'staff_id' => $recipientData['staff_id'],
                    'department' => $recipientData['recipient_department'],
                    'designation' => $recipientData['designation'],
                ]);
            }
        }

        $category = $user->role === AppUser::ROLE_RECIPIENT ? 'recipients' : 'students';

        AuditLog::activity(
            'account_updated',
            details: sprintf('Updated %s account for %s.', $user->role, $user->name)
        );

        if ($user->email) {
            $user->notify(new AccountUpdateNotification(
                'Your SORSUPPORT account was updated',
                'An administrator updated your account information.'
            ));
        }

        return redirect()
            ->route('admin.accounts.index', ['category_filter' => $category]);
    }

    /**
     * Deactivate an account.
     */
    public function deactivate(AppUser $user)
    {
        $user->update([
            'is_active' => false,
        ]);

        $category = $user->role === AppUser::ROLE_RECIPIENT ? 'recipients' : 'students';

        AuditLog::activity(
            'account_deactivated',
            details: sprintf('Deactivated %s account for %s.', $user->role, $user->name)
        );

        if ($user->email) {
            $user->notify(new AccountUpdateNotification(
                'Your SORSUPPORT account was deactivated',
                'Your account has been deactivated by an administrator. Contact Student Development Services if you need assistance.'
            ));
        }

        return redirect()
            ->route('admin.accounts.index', ['category_filter' => $category]);
    }

    /**
     * Reactivate an account.
     */
    public function reactivate(AppUser $user)
    {
        if (! $user->hasCompleteProfile()) {
            return redirect()
                ->route('admin.accounts.index', ['category_filter' => $user->role === AppUser::ROLE_RECIPIENT ? 'recipients' : 'students'])
                ->withErrors(['account' => 'Complete the account information before activating it.']);
        }

        $user->update([
            'is_active' => true,
            'email_verified_at' => $user->email_verified_at ?? now(),
        ]);

        $category = $user->role === AppUser::ROLE_RECIPIENT ? 'recipients' : 'students';

        AuditLog::activity(
            'account_activated',
            details: sprintf('Activated %s account for %s.', $user->role, $user->name)
        );

        if ($user->email) {
            $user->notify(new AccountUpdateNotification(
                'Your SORSUPPORT account was activated',
                'Your account has been activated and is available for use.'
            ));
        }

        return redirect()
            ->route('admin.accounts.index', ['category_filter' => $category]);
    }

    /**
     * Show upload form.
     */
    public function showUploadForm()
    {
        return view('admin.accounts.upload');
    }

    /**
     * Import student accounts.
     */
    public function upload(Request $request)
    {
        $validated = $request->validate([
            'account_type' => 'nullable|in:student,recipient',
            'file' => 'required|file|mimes:csv,xlsx,xls|max:10240',
        ]);
        $explicitAccountType = $request->filled('account_type');
        $validated['account_type'] ??= AppUser::ROLE_STUDENT;

        $import = new AccountsImport($validated['account_type']);

        try {
            Excel::import($import, $request->file('file'));
            $summary = $import->summary();
        } catch (\Throwable $exception) {
            return back()->withErrors(['file' => 'The uploaded file could not be read. Use the provided template and try again.']);
        }

        $redirectParameters = $explicitAccountType
            ? ['category_filter' => $validated['account_type'] === AppUser::ROLE_RECIPIENT ? 'recipients' : 'students']
            : [];

        AuditLog::activity(
            'accounts_imported',
            details: sprintf(
                'Imported %d %s account(s).',
                $summary['imported'],
                $validated['account_type']
            )
        );

        return redirect()
            ->route('admin.accounts.index', $redirectParameters)
            ->with('upload_summary', $summary)
            ->with('upload_account_type', $validated['account_type']);
    }

    public function downloadUploadErrors(Request $request)
    {
        $summary = $request->session()->get('upload_summary');
        abort_unless(is_array($summary) && ! empty($summary['errors']), 404);

        return response()->streamDownload(function () use ($summary): void {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Row', 'Error']);
            foreach ($summary['errors'] as $error) {
                fputcsv($handle, [$error['row'], implode(' ', $error['messages'])]);
            }
            fclose($handle);
        }, 'bulk-upload-errors.csv', ['Content-Type' => 'text/csv']);
    }
}