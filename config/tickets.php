<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Reminders for waiting tickets
    |--------------------------------------------------------------------------
    |
    | Starting values for the reminder settings. The SDS admin changes them in System Settings,
    | which overrides these. Days are working days (Monday to Friday). The reminder repeats
    | every that many days until someone acts on the ticket.
    |
    */

    'reminders' => [
        'enabled' => true,

        // Submitted and not yet classified or assigned: the SDS Office is reminded.
        'unreviewed_days' => 2,

        // Internal SDS follow-up informed by the CODI 24-hour notice timeline (Handbook p. 58).
        // Adjustable; not an official committee deadline or compliance measure.
        'sensitive_unreviewed_days' => 1,

        // Internal SDS follow-up while awaiting the student's clarification; not a committee deadline.
        'clarification_days' => 5,

        // Assigned and not yet acknowledged: the assigned handler is reminded.
        'unacknowledged_days' => 2,

        // In progress or escalated with no action recorded: the handler is reminded.
        'idle_days' => 5,

        // The handbook's 10-working-day period applies only to CODI referrals (pp. 57 to 58).
        'referral_days' => 10,

        // Internal SDS follow-up for another referral; not a committee deadline or compliance measure.
        'generic_referral_enabled' => false,
        'generic_referral_days' => 10,

        // System default for the student's response period after resolution, adjustable by the
        // SDS admin in System Settings.
        'student_response_days' => \App\Models\Ticket::FURTHER_ACTION_DAYS,
    ],

    /*
    |--------------------------------------------------------------------------
    | Submission limit
    |--------------------------------------------------------------------------
    |
    | How many tickets one student may submit within an hour, so the queue cannot be flooded.
    |
    */

    'submissions_per_hour' => (int) env('TICKET_SUBMISSIONS_PER_HOUR', 5),

];
