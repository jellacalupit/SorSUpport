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

        // The same for a ticket in a sensitive category. The Student Handbook (p. 58) requires
        // a sexual harassment complaint to be acted on within 24 hours of receipt.
        'sensitive_unreviewed_days' => 1,

        // Assigned and not yet acknowledged: the assigned handler is reminded.
        'unacknowledged_days' => 2,

        // In progress or escalated with no action recorded: the handler is reminded.
        'idle_days' => 5,

        // Referred to a committee with no outcome recorded: the SDS Office is reminded to follow
        // up. The Student Handbook (p. 57) gives the committee 10 working days to decide.
        'referral_days' => 10,

        // Calendar days a student has to accept a resolution or ask for further action, after
        // which the SDS Office is told once so it can close the ticket. The Student Handbook
        // (pp. 47–48) gives 15 days to appeal a decision.
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
