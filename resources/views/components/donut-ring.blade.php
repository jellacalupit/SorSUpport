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
    $animationCursorMs = 0;

    foreach ($segments as $segment) {
        if ($total <= 0 || $segment['count'] <= 0) {
            continue;
        }

        $end = $start + $segment['count'] / $total;
        $segmentDurationMs = max(35, (int) round(($end - $start) * 320));
        $arcs[] = [
            'label' => $segment['label'],
            'count' => (int) $segment['count'],
            'percentage' => (int) round($segment['count'] / $total * 100),
            'color' => $segment['color'],
            'full' => $segment['count'] === $total,
            'large' => ($end - $start) > 0.5 ? 1 : 0,
            'from' => $pointAt($start),
            'to' => $pointAt($end),
            'delay_ms' => $animationCursorMs,
            'duration_ms' => $segmentDurationMs,
            'cap_delay_ms' => $animationCursorMs + $segmentDurationMs,
        ];
        $animationCursorMs += $segmentDurationMs;
        $start = $end;
    }
@endphp

<span
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
                    pathLength="100"
                    class="dashboard-donut-segment-reveal cursor-default transition-opacity hover:opacity-80"
                    style="--donut-delay: {{ $arc['delay_ms'] }}ms; --donut-duration: {{ $arc['duration_ms'] }}ms;"
                    cx="50"
                    cy="50"
                    r="{{ $radius }}"
                    fill="none"
                    stroke="{{ $arc['color'] }}"
                    stroke-width="{{ $strokeWidth }}"
                    transform="translate(100 0) scale(-1 1) rotate(-90 50 50)"
                    x-on:mouseenter="$dispatch('donut-segment-enter', { label: $event.currentTarget.dataset.label, count: Number($event.currentTarget.dataset.count), percentage: Number($event.currentTarget.dataset.percentage) })"
                    x-on:mouseleave="$dispatch('donut-segment-leave')"
                />
            @else
                <path
                    data-donut-segment
                    data-label="{{ $arc['label'] }}"
                    data-count="{{ $arc['count'] }}"
                    data-percentage="{{ $arc['percentage'] }}"
                    pathLength="100"
                    class="dashboard-donut-segment-reveal cursor-default transition-opacity hover:opacity-80"
                    style="--donut-delay: {{ $arc['delay_ms'] }}ms; --donut-duration: {{ $arc['duration_ms'] }}ms;"
                    d="M {{ $arc['from'][0] }} {{ $arc['from'][1] }} A {{ $radius }} {{ $radius }} 0 {{ $arc['large'] }} 0 {{ $arc['to'][0] }} {{ $arc['to'][1] }}"
                    fill="none"
                    stroke="{{ $arc['color'] }}"
                    stroke-width="{{ $strokeWidth }}"
                    x-on:mouseenter="$dispatch('donut-segment-enter', { label: $event.currentTarget.dataset.label, count: Number($event.currentTarget.dataset.count), percentage: Number($event.currentTarget.dataset.percentage) })"
                    x-on:mouseleave="$dispatch('donut-segment-leave')"
                />
            @endif
        @endforeach

        {{-- Each colour owns its rounded end cap. Caps appear only after their matching arc finishes,
             and the final cap is naturally rendered last above the first segment at 12 o'clock. --}}
        @foreach ($arcs as $arc)
            @unless ($arc['full'])
                <circle
                    class="dashboard-donut-cap-reveal pointer-events-none"
                    style="--donut-cap-delay: {{ $arc['cap_delay_ms'] }}ms;"
                    cx="{{ $arc['to'][0] }}"
                    cy="{{ $arc['to'][1] }}"
                    r="{{ $strokeWidth / 2 }}"
                    fill="{{ $arc['color'] }}"
                />
            @endunless
        @endforeach
    </svg>

</span>
