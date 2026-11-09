<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\CalendarOverride;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class WorkingCalendarController extends Controller
{
    public function index(Request $request): View
    {
        $validated = $request->validate([
            'month' => ['nullable', 'date_format:Y-m'],
        ]);
        $timezone = config('app.timezone');
        $month = Carbon::parse(($validated['month'] ?? now($timezone)->format('Y-m')) . '-01', $timezone)->startOfMonth();
        $monthEnd = $month->copy()->endOfMonth();
        $gridStart = $month->copy()->startOfWeek(Carbon::SUNDAY);
        $gridEnd = $monthEnd->copy()->endOfWeek(Carbon::SATURDAY);
        $overrides = CalendarOverride::query()
            ->whereBetween('date', [$month->toDateString(), $monthEnd->toDateString()])
            ->orderBy('date')
            ->get();
        $overridesByDate = $overrides->keyBy(fn (CalendarOverride $override) => $override->date->toDateString());
        $calendarDays = collect();

        for ($day = $gridStart->copy(); $day->lte($gridEnd); $day->addDay()) {
            $date = $day->toDateString();
            $override = $overridesByDate->get($date);
            $calendarDays->push([
                'date' => $date,
                'day' => $day->day,
                'in_month' => $day->month === $month->month,
                'is_today' => $date === now($timezone)->toDateString(),
                'is_weekend' => $day->isWeekend(),
                'override_id' => $override?->id,
                'type' => $override?->type,
                'description' => $override?->description,
            ]);
        }

        return view('admin.working-calendar', [
            'month' => $month,
            'calendarDays' => $calendarDays,
            'overrides' => $overrides,
            'previousMonth' => $month->copy()->subMonth()->format('Y-m'),
            'nextMonth' => $month->copy()->addMonth()->format('Y-m'),
            'todayMonth' => now($timezone)->format('Y-m'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'date' => ['required', 'date_format:Y-m-d'],
            'type' => ['required', 'in:' . CalendarOverride::TYPE_NON_WORKING . ',' . CalendarOverride::TYPE_WORKING],
            'description' => ['required', 'string', 'max:255'],
        ]);

        DB::transaction(fn () => $this->saveOverride($validated, $request));

        return $this->calendarRedirect($validated['date'])->with('success', 'Working Calendar override saved.');
    }

    public function storeRange(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'start_date' => ['required', 'date_format:Y-m-d'],
            'end_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:start_date'],
            'type' => ['required', 'in:' . CalendarOverride::TYPE_NON_WORKING . ',' . CalendarOverride::TYPE_WORKING],
            'description' => ['required', 'string', 'max:255'],
        ]);

        $start = Carbon::parse($validated['start_date'], config('app.timezone'));
        $end = Carbon::parse($validated['end_date'], config('app.timezone'));
        if ($start->diffInDays($end) + 1 > 60) {
            return back()
                ->withErrors(['end_date' => 'A Working Calendar range cannot exceed 60 days.'])
                ->withInput();
        }

        DB::transaction(function () use ($validated, $request, $start, $end): void {
            for ($date = $start->copy(); $date->lte($end); $date->addDay()) {
                $this->saveOverride([
                    'date' => $date->toDateString(),
                    'type' => $validated['type'],
                    'description' => $validated['description'],
                ], $request);
            }
        });

        return $this->calendarRedirect($validated['start_date'])->with('success', 'Working Calendar range saved.');
    }

    public function destroy(CalendarOverride $calendarOverride): RedirectResponse
    {
        $date = $calendarOverride->date->toDateString();
        $type = $calendarOverride->type;
        $description = $calendarOverride->description;

        DB::transaction(function () use ($calendarOverride, $date, $type, $description): void {
            $calendarOverride->delete();
            AuditLog::activity(
                'calendar_override_removed',
                details: "Removed {$type} override for {$date}. Description: {$description}",
            );
        });

        return $this->calendarRedirect($date)->with('success', 'Working Calendar override removed.');
    }

    private function saveOverride(array $attributes, Request $request): void
    {
        $override = CalendarOverride::query()->whereDate('date', $attributes['date'])->first();
        if (! $override) {
            $override = CalendarOverride::create($attributes + ['created_by' => $request->user()->id]);
            AuditLog::activity(
                'calendar_override_created',
                details: $this->auditDetails($attributes),
            );

            return;
        }

        if ($override->type === $attributes['type'] && $override->description === $attributes['description']) {
            return;
        }

        $override->update($attributes);
        AuditLog::activity(
            'calendar_override_updated',
            details: $this->auditDetails($attributes),
        );
    }

    private function auditDetails(array $attributes): string
    {
        return "Set {$attributes['type']} override for {$attributes['date']}. Description: {$attributes['description']}";
    }

    private function calendarRedirect(string $date): RedirectResponse
    {
        return redirect()->route('admin.working-calendar', [
            'month' => Carbon::parse($date, config('app.timezone'))->format('Y-m'),
        ]);
    }
}
