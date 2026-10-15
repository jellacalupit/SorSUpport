<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Reminders for waiting tickets
    |--------------------------------------------------------------------------
    |
    | Once a day the people holding a ticket are emailed when it has waited this many days with
    | nothing recorded on it. The reminder repeats every that many days until someone acts.
    |
    */

    'reminders' => [
        // Submitted and not yet classified or assigned: the SDS Office is reminded.
        'unreviewed_days' => (int) env('REMIND_UNREVIEWED_AFTER_DAYS', 2),

        // Assigned and not yet acknowledged: the assigned handler is reminded.
        'unacknowledged_days' => (int) env('REMIND_UNACKNOWLEDGED_AFTER_DAYS', 2),

        // In progress or escalated with no action recorded: the handler is reminded.
        'idle_days' => (int) env('REMIND_IDLE_AFTER_DAYS', 5),

        // Time of day the reminders go out, in the app's timezone.
        'send_at' => env('REMINDERS_SEND_AT', '08:00'),
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
