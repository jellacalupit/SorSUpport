@props(['deadline'])

@php
    if (!$deadline) {
        $__empty = true;
    } else {
        $now = now()->copy()->setTimezone('Asia/Manila')->startOfDay();
        $daysLeft = $deadline->copy()->setTimezone('Asia/Manila')->startOfDay()->diffInDays($now, false);
        $__empty = false;
        
        if ($daysLeft < 0) {
            $tone = 'border-destructive bg-destructive text-destructive-foreground';
            $message = 'Overdue by ' . abs($daysLeft) . ' day' . (abs($daysLeft) === 1 ? '' : 's');
        } elseif ($daysLeft === 0) {
            $tone = 'border-destructive/40 bg-destructive/10 text-destructive';
            $message = 'Due today';
        } elseif ($daysLeft <= 1) {
            $tone = 'border-destructive/40 bg-destructive/10 text-destructive';
            $message = $daysLeft . ' day remaining';
        } elseif ($daysLeft <= 3) {
            $tone = 'border-warning/50 bg-warning/15 text-warning-foreground';
            $message = $daysLeft . ' day' . ($daysLeft === 1 ? '' : 's') . ' remaining';
        } else {
            $tone = 'border-border bg-muted text-muted-foreground';
            $message = $daysLeft . ' day' . ($daysLeft === 1 ? '' : 's') . ' remaining';
        }
    }
@endphp

@if (!$__empty)
    <span {{ $attributes->merge(['class' => "inline-flex items-center gap-1.5 rounded-lg border px-2.5 py-1 text-xs font-semibold {$tone}"]) }}>
        {{ $message }}
    </span>
@endif
