<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Unit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class UnitController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'type' => ['required', Rule::in([Unit::TYPE_COLLEGE, Unit::TYPE_OFFICE])],
            ...$this->rules($request->input('type')),
            'name' => ['required', 'string', 'max:255', Rule::unique('units', 'name')],
        ], $this->messages());

        $unit = DB::transaction(function () use ($validated): Unit {
            $unit = Unit::create([
                'name' => trim($validated['name']),
                'type' => $validated['type'],
                'description' => $this->cleanText($validated['description'] ?? null),
            ]);

            $this->syncDetails($unit, $validated);

            return $unit;
        });

        AuditLog::activity('unit_created', details: sprintf('Created %s "%s".', $unit->type, $unit->name));

        return redirect()->route('admin.settings', ['settings_tab' => 'units'])
            ->with('success', ($unit->isCollege() ? 'College' : 'Office') . ' created successfully.');
    }

    public function update(Request $request, Unit $unit): RedirectResponse
    {
        $validated = $request->validate([
            ...$this->rules($unit->type),
            'name' => ['required', 'string', 'max:255', Rule::unique('units', 'name')->ignore($unit->id)],
        ], $this->messages());

        DB::transaction(function () use ($unit, $validated): void {
            $unit->update([
                'name' => trim($validated['name']),
                'description' => $this->cleanText($validated['description'] ?? null),
            ]);

            $unit->programs()->delete();
            $unit->designations()->delete();

            $this->syncDetails($unit, $validated);
        });

        AuditLog::activity('unit_updated', details: sprintf('Updated %s "%s".', $unit->type, $unit->name));

        return redirect()->route('admin.settings', ['settings_tab' => 'units'])
            ->with('success', ($unit->isCollege() ? 'College' : 'Office') . ' updated successfully.');
    }

    public function destroy(Unit $unit): RedirectResponse
    {
        $label = $unit->isCollege() ? 'College' : 'Office';
        $name = $unit->name;
        $type = $unit->type;
        $unit->delete();

        AuditLog::activity('unit_deleted', details: sprintf('Deleted %s "%s".', $type, $name));

        return redirect()->route('admin.settings', ['settings_tab' => 'units'])
            ->with('success', $label . ' deleted successfully.');
    }

    /**
     * A college must offer at least one program; any unit may list staff designations.
     */
    protected function rules(?string $type): array
    {
        $isCollege = $type === Unit::TYPE_COLLEGE;

        return [
            'description' => ['nullable', 'string', 'max:2000'],
            'programs' => [$isCollege ? 'required' : 'nullable', 'array'],
            'programs.*.name' => [$isCollege ? 'required' : 'nullable', 'string', 'max:255', 'distinct'],
            'programs.*.year_level' => [$isCollege ? 'required' : 'nullable', 'integer', 'between:1,6'],
            'programs.*.block' => ['nullable', 'integer', 'between:1,10'],
            'programs.*.description' => ['nullable', 'string', 'max:2000'],
            'designations' => ['nullable', 'array'],
            'designations.*.name' => ['nullable', 'string', 'max:255', 'distinct'],
            'designations.*.description' => ['nullable', 'string', 'max:2000'],
        ];
    }

    protected function messages(): array
    {
        return [
            'name.unique' => 'A college or office with this name already exists.',
            'programs.required' => 'Add at least one program.',
        ];
    }

    protected function syncDetails(Unit $unit, array $validated): void
    {
        if ($unit->isCollege()) {
            foreach ($validated['programs'] ?? [] as $program) {
                $unit->programs()->create([
                    'name' => trim($program['name']),
                    'year_level' => $program['year_level'],
                    'block' => filled($program['block'] ?? null) ? $program['block'] : null,
                    'description' => $this->cleanText($program['description'] ?? null),
                ]);
            }
        }

        foreach ($validated['designations'] ?? [] as $designation) {
            $name = trim((string) ($designation['name'] ?? ''));

            if ($name !== '') {
                $unit->designations()->create([
                    'name' => $name,
                    'description' => $this->cleanText($designation['description'] ?? null),
                ]);
            }
        }
    }

    protected function cleanText(?string $value): ?string
    {
        return trim((string) $value) ?: null;
    }
}
