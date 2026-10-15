<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Services\TicketReminderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ReminderSettingsController extends Controller
{
    /**
     * Save when reminders for waiting tickets go out.
     */
    public function update(Request $request, TicketReminderService $reminders): RedirectResponse
    {
        $days = ['required', 'integer', 'min:1', 'max:30'];

        $validated = $request->validate([
            'enabled' => ['required', 'boolean'],
            'unreviewed_days' => $days,
            'sensitive_unreviewed_days' => $days,
            'unacknowledged_days' => $days,
            'idle_days' => $days,
            'referral_days' => $days,
            'student_response_days' => $days,
        ], [
            '*.min' => 'Enter at least 1 day.',
            '*.max' => 'Enter at most 30 days.',
        ]);

        $reminders->saveSettings($validated);

        $studentDays = sprintf('Students have %d days to respond to a resolution.', $validated['student_response_days']);

        AuditLog::activity('reminder_settings_updated', details: $validated['enabled']
            ? sprintf(
                'Updated reminders: not reviewed %d, sensitive not reviewed %d, not acknowledged %d, no action %d, referred %d working days. %s',
                $validated['unreviewed_days'],
                $validated['sensitive_unreviewed_days'],
                $validated['unacknowledged_days'],
                $validated['idle_days'],
                $validated['referral_days'],
                $studentDays,
            )
            : 'Turned off reminders for waiting tickets. ' . $studentDays);

        return redirect()->route('admin.settings', ['settings_tab' => 'reminders'])
            ->with('success', 'Reminder settings saved.');
    }
}
