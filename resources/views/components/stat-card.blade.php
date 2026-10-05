@props([
    'label',
    'value',
    'hint' => null,
    'tone' => 'default', // default, primary, danger
    'valueTone' => 'default', // default, yellow, blue, red, green
    'compact' => false,
    // compact only: label and value side by side.
    // true = on phones (below 640px); 'narrow' = only on very narrow screens (below 360px)
    'inline' => false,
])

@php
    $inlineOnPhones = $compact && $inline === true;
    $inlineWhenNarrow = $compact && $inline === 'narrow';
@endphp

<div @class([
    'surface border border-border bg-white shadow-md',
    'px-2 py-2.5 text-center sm:px-3 sm:py-4' => $compact && ! $inline,
    'flex items-center justify-between gap-2 px-3 py-2.5 sm:block sm:py-4 sm:text-center' => $inlineOnPhones,
    // Stacked cards keep the number at the bottom so it lines up even when a label wraps.
    'flex items-center justify-between gap-2 px-3 py-2.5 min-[360px]:flex-col min-[360px]:gap-0.5 min-[360px]:px-1.5 min-[360px]:text-center sm:px-3 sm:py-4' => $inlineWhenNarrow,
    'p-4' => !$compact,
])>
    <p @class([
        'font-semibold tracking-wide uppercase',
        'text-black' => $valueTone === 'default',
        'text-[9px] leading-tight sm:text-[11px]' => $compact,
        'min-w-0 text-left sm:text-center' => $inlineOnPhones,
        'min-w-0 text-left min-[360px]:text-center' => $inlineWhenNarrow,
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
        'mt-0.5 text-xl sm:text-2xl' => $compact && ! $inline,
        'shrink-0 text-xl leading-none sm:mt-0.5 sm:text-2xl sm:leading-8' => $inlineOnPhones,
        'shrink-0 text-xl leading-none min-[360px]:leading-7 sm:text-2xl sm:leading-8' => $inlineWhenNarrow,
        'mt-1.5 text-3xl' => !$compact,
        'text-[#800000]' => true,
    ])>
        {{ $value }}
    </p>

    @if($hint && !$compact)
        <p class="mt-1 text-xs text-muted-foreground">{{ $hint }}</p>
    @endif
</div>
