@props([
    'label',
    'value',
    'hint' => null,
    'tone' => 'default', // default, primary, danger
    'valueTone' => 'default', // default, yellow, blue, red, green
    'compact' => false,
    'inline' => false, // compact only: label and value side by side on mobile
])

<div @class([
    'surface border border-border bg-white shadow-md',
    'px-2 py-2.5 text-center sm:px-3 sm:py-4' => $compact && !$inline,
    'flex items-center justify-between gap-2 px-3 py-2.5 sm:block sm:py-4 sm:text-center' => $compact && $inline,
    'p-4' => !$compact,
])>
    <p @class([
        'font-semibold tracking-wide uppercase',
        'text-black' => $valueTone === 'default',
        'text-[9px] leading-tight sm:text-[11px]' => $compact,
        'min-w-0 text-left sm:text-center' => $compact && $inline,
        'text-xs' => !$compact,
        'text-yellow-700' => $valueTone === 'yellow',
        'text-blue-700' => $valueTone === 'blue',
        'text-red-700' => $valueTone === 'red',
        'text-green-700' => $valueTone === 'green',
    ])>
        {{ $label }}
    </p>

    <p @class([
        'font-display font-bold',
        'mt-0.5 text-xl sm:text-2xl' => $compact && !$inline,
        'shrink-0 text-xl leading-none sm:mt-0.5 sm:text-2xl sm:leading-8' => $compact && $inline,
        'mt-1.5 text-3xl' => !$compact,
        'text-[#800000]' => true,
    ])>
        {{ $value }}
    </p>

    @if($hint && !$compact)
        <p class="mt-1 text-xs text-muted-foreground">{{ $hint }}</p>
    @endif
</div>
