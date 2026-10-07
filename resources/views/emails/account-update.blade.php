{{-- Account notices (activated, deactivated, updated) share the design of the ticket emails. --}}
@include('emails.notifications.layout', ['lines' => [$email_message]])
