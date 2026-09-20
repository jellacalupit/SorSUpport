<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Department;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DepartmentController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:departments,name'],
            'positions' => ['nullable', 'array'],
            'positions.*' => ['nullable', 'string', 'max:255', 'distinct'],
        ]);

        $department = DB::transaction(function () use ($validated): Department {
            $department = Department::create(['name' => trim($validated['name'])]);

            foreach ($validated['positions'] ?? [] as $position) {
                $position = trim($position);
                if ($position !== '') {
                    $department->positions()->create(['name' => $position]);
                }
            }

            return $department;
        });

        AuditLog::activity('department_created', details: sprintf('Created department "%s".', $department->name));

        return redirect()->route('admin.settings', ['settings_tab' => 'department'])
            ->with('success', 'Department created successfully.');
    }
}
