@props([
    'segments' => [], // each: ['label' => ..., 'color' => ..., 'count' => ...]
    'strokeWidth' => 12,
])

@php
    $radius = 38;
    $strokeWidth = (float) $strokeWidth;
    $total = array_sum(array_column($segments, 'count'));

    // The ring runs counter-clockwise from the top. Each colour ends in a rounded tip that laps
    // over the start of the next colour, so only the leading end of a colour is curved.
    $pointAt = fn (float $fraction) => [
        round(50 - $radius * sin($fraction * 2 * pi()), 3),
        round(50 - $radius * cos($fraction * 2 * pi()), 3),
    ];

    $arcs = [];
    $start = 0.0;

    foreach ($segments as $segment) {
        if ($total <= 0 || $segment['count'] <= 0) {
            continue;
        }

        $end = $start + $segment['count'] / $total;
        $arcs[] = [
            'label' => $segment['label'],
            'count' => (int) $segment['count'],
            'percentage' => (int) round($segment['count'] / $total * 100),
            'color' => $segment['color'],
            'full' => $segment['count'] === $total,
            'large' => ($end - $start) > 0.5 ? 1 : 0,
            'from' => $pointAt($start),
            'to' => $pointAt($end),
        ];
        $start = $end;
    }
@endphp

<span
    x-data="{ tooltip: null, tooltipX: 0, tooltipY: 0 }"
    x-on:mousemove="const bounds = $root.getBoundingClientRect(); tooltipX = $event.clientX - bounds.left; tooltipY = $event.clientY - bounds.top"
    {{ $attributes->class(['relative block h-full w-full']) }}
>
    <svg viewBox="0 0 100 100" class="h-full w-full" aria-hidden="true">
        <circle cx="50" cy="50" r="{{ $radius }}" fill="none" stroke="#eef2f7" stroke-width="{{ $strokeWidth }}" />
        @foreach ($arcs as $arc)
            @if ($arc['full'])
                <circle
                    data-donut-segment
                    data-label="{{ $arc['label'] }}"
                    data-count="{{ $arc['count'] }}"
                    data-percentage="{{ $arc['percentage'] }}"
                    class="cursor-help transition-opacity hover:opacity-80"
                    cx="50"
                    cy="50"
                    r="{{ $radius }}"
                    fill="none"
                    stroke="{{ $arc['color'] }}"
                    stroke-width="{{ $strokeWidth }}"
                    x-on:mouseenter="tooltip = { label: $event.currentTarget.dataset.label, count: Number($event.currentTarget.dataset.count), percentage: Number($event.currentTarget.dataset.percentage) }"
                    x-on:mouseleave="tooltip = null"
                />
            @else
                <path
                    data-donut-segment
                    data-label="{{ $arc['label'] }}"
                    data-count="{{ $arc['count'] }}"
                    data-percentage="{{ $arc['percentage'] }}"
                    class="cursor-help transition-opacity hover:opacity-80"
                    d="M {{ $arc['from'][0] }} {{ $arc['from'][1] }} A {{ $radius }} {{ $radius }} 0 {{ $arc['large'] }} 0 {{ $arc['to'][0] }} {{ $arc['to'][1] }}"
                    fill="none"
                    stroke="{{ $arc['color'] }}"
                    stroke-width="{{ $strokeWidth }}"
                    x-on:mouseenter="tooltip = { label: $event.currentTarget.dataset.label, count: Number($event.currentTarget.dataset.count), percentage: Number($event.currentTarget.dataset.percentage) }"
                    x-on:mouseleave="tooltip = null"
                />
            @endif
        @endforeach
        {{-- Rounded tips go on top of every arc so they overlap the colour that follows. --}}
        @foreach ($arcs as $arc)
            @unless ($arc['full'])
                <circle class="pointer-events-none" cx="{{ $arc['to'][0] }}" cy="{{ $arc['to'][1] }}" r="{{ $strokeWidth / 2 }}" fill="{{ $arc['color'] }}" />
            @endunless
        @endforeach
    </svg>

    <span
        x-cloak
        x-show="tooltip"
        class="pointer-events-none absolute z-30 min-w-max rounded-lg border border-border bg-white px-2.5 py-1.5 text-left shadow-lg"
        x-bind:style="`left: ${tooltipX}px; top: ${tooltipY}px; transform: translate(-50%, calc(-100% - 8px));`"
    >
        <span class="block text-[11px] font-medium leading-tight text-muted-foreground" x-text="tooltip?.label"></span>
        <span class="mt-0.5 block text-xs font-semibold leading-tight text-primary">
            <span x-text="tooltip?.count"></span>
            <span x-text="` ticket${tooltip?.count === 1 ? '' : 's'} · ${tooltip?.percentage}%`"></span>
        </span>
    </span>
</span>
