<x-app-layout :role="'admin'" title="Ticket Details">
    @if ($errors->any())
        <div class="mb-4 rounded-lg border border-destructive/30 bg-destructive/10 p-4 text-sm text-destructive">
            <ul class="list-inside list-disc space-y-1">
                @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
            </ul>
        </div>
    @endif

    @if ($complaint->ticket)
        <x-admin-ticket-view :ticket="$complaint->ticket" :back-url="route('admin.complaints.index')" :categories="$categories" :recipients="$recipients" />
    @else
        <div class="rounded-lg border border-dashed p-10 text-center text-sm text-muted-foreground">No ticket has been generated for this complaint yet.</div>
    @endif
</x-app-layout>
