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
        ], [
            '*.min' => 'Enter at least 1 working day.',
            '*.max' => 'Enter at most 30 working days.',
        ]);

        $reminders->saveSettings($validated);

        AuditLog::activity('reminder_settings_updated', details: $validated['enabled']
            ? sprintf(
                'Updated reminders: not reviewed %d, sensitive not reviewed %d, not acknowledged %d, no action %d, referred %d working days.',
                $validated['unreviewed_days'],
                $validated['sensitive_unreviewed_days'],
                $validated['unacknowledged_days'],
                $validated['idle_days'],
                $validated['referral_days'],
            )
            : 'Turned off reminders for waiting tickets.');

        return redirect()->route('admin.settings', ['settings_tab' => 'reminders'])
            ->with('success', 'Reminder settings saved.');
    }
}
