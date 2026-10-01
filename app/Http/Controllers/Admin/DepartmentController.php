<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Department;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class DepartmentController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'type' => ['required', 'in:student,recipient'],
            'name' => ['required', 'string', 'max:255', Rule::unique('departments', 'name')->where(fn ($query) => $query->where('type', $request->input('type')))],
            'description' => ['nullable', 'string', 'max:2000'],
            'courses' => ['required_if:type,student', 'nullable', 'array'],
            'courses.*.course' => ['required_if:type,student', 'nullable', 'string', 'max:255'],
            'courses.*.year_level' => ['required_if:type,student', 'nullable', 'integer', 'between:1,6'],
            'courses.*.block' => ['nullable', 'integer', 'between:1,10'],
            'courses.*.description' => ['nullable', 'string', 'max:2000'],
            'positions' => ['nullable', 'array'],
            'positions.*' => ['nullable'],
            'positions.*.name' => ['nullable', 'string', 'max:255', 'distinct'],
            'positions.*.description' => ['nullable', 'string', 'max:2000'],
        ]);

        $department = DB::transaction(function () use ($validated): Department {
            $department = Department::create([
                'name' => trim($validated['name']),
                'type' => $validated['type'],
                'description' => isset($validated['description']) ? trim($validated['description']) ?: null : null,
            ]);

            foreach ($validated['courses'] ?? [] as $course) {
                $department->courses()->create([
                    'course' => trim($course['course']),
                    'year_level' => $course['year_level'],
                    'block' => isset($course['block']) ? trim($course['block']) ?: null : null,
                    'description' => isset($course['description']) ? trim($course['description']) ?: null : null,
                ]);
            }

            foreach ($validated['positions'] ?? [] as $position) {
                $name = is_string($position) ? $position : ($position['name'] ?? '');
                $description = is_string($position) ? null : ($position['description'] ?? null);
                if (trim($name) !== '') {
                    $department->positions()->create(['name' => trim($name), 'description' => trim($description ?? '') ?: null]);
                }
            }

            return $department;
        });

        AuditLog::activity('department_created', details: sprintf('Created department "%s".', $department->name));

        return redirect()->route('admin.settings', ['settings_tab' => 'department'])
            ->with('success', 'Department created successfully.');
    }

    public function update(Request $request, Department $department): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('departments', 'name')->where(fn ($query) => $query->where('type', $department->type))->ignore($department->id)],
            'description' => ['nullable', 'string', 'max:2000'],
            'courses' => ['required_if:type,student', 'nullable', 'array'],
            'courses.*.course' => ['required_if:type,student', 'nullable', 'string', 'max:255'],
            'courses.*.year_level' => ['required_if:type,student', 'nullable', 'integer', 'between:1,6'],
            'courses.*.block' => ['nullable', 'integer', 'between:1,10'],
            'courses.*.description' => ['nullable', 'string', 'max:2000'],
            'positions' => ['nullable', 'array'],
            'positions.*' => ['nullable'],
            'positions.*.name' => ['nullable', 'string', 'max:255', 'distinct'],
            'positions.*.description' => ['nullable', 'string', 'max:2000'],
        ]);

        DB::transaction(function () use ($department, $validated): void {
            $department->update([
                'name' => trim($validated['name']),
                'description' => isset($validated['description']) ? trim($validated['description']) ?: null : null,
            ]);
            $department->courses()->delete();

            foreach ($validated['courses'] ?? [] as $course) {
                $department->courses()->create([
                    'course' => trim($course['course']),
                    'year_level' => $course['year_level'],
                    'block' => isset($course['block']) ? trim((string) $course['block']) ?: null : null,
                    'description' => isset($course['description']) ? trim($course['description']) ?: null : null,
                ]);
            }

            $department->positions()->delete();
            foreach ($validated['positions'] ?? [] as $position) {
                $name = is_string($position) ? $position : ($position['name'] ?? '');
                $description = is_string($position) ? null : ($position['description'] ?? null);
                if (trim($name) !== '') {
                    $department->positions()->create(['name' => trim($name), 'description' => trim($description ?? '') ?: null]);
                }
            }
        });

        AuditLog::activity('department_updated', details: sprintf('Updated department "%s".', $department->name));

        return redirect()->route('admin.settings', ['settings_tab' => 'department'])
            ->with('success', 'Department updated successfully.');
    }

    public function destroy(Department $department): RedirectResponse
    {
        $departmentName = $department->name;
        $department->delete();

        AuditLog::activity('department_deleted', details: sprintf('Deleted department "%s".', $departmentName));

        return redirect()->route('admin.settings', ['settings_tab' => 'department'])
            ->with('success', 'Department deleted successfully.');
    }
}
