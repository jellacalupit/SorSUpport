@props(['segments' => []]) {{-- each: ['label' => ..., 'color' => ..., 'count' => ...] --}}

@php
    $radius = 38;
    $strokeWidth = 12;
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
            'color' => $segment['color'],
            'full' => $segment['count'] === $total,
            'large' => ($end - $start) > 0.5 ? 1 : 0,
            'from' => $pointAt($start),
            'to' => $pointAt($end),
        ];
        $start = $end;
    }
@endphp

<svg viewBox="0 0 100 100" {{ $attributes->merge(['class' => 'h-full w-full']) }} aria-hidden="true">
    <circle cx="50" cy="50" r="{{ $radius }}" fill="none" stroke="#eef2f7" stroke-width="{{ $strokeWidth }}" />
    @foreach ($arcs as $arc)
        @if ($arc['full'])
            <circle cx="50" cy="50" r="{{ $radius }}" fill="none" stroke="{{ $arc['color'] }}" stroke-width="{{ $strokeWidth }}" />
        @else
            <path d="M {{ $arc['from'][0] }} {{ $arc['from'][1] }} A {{ $radius }} {{ $radius }} 0 {{ $arc['large'] }} 0 {{ $arc['to'][0] }} {{ $arc['to'][1] }}" fill="none" stroke="{{ $arc['color'] }}" stroke-width="{{ $strokeWidth }}" />
        @endif
    @endforeach
    {{-- Rounded tips go on top of every arc so they overlap the colour that follows. --}}
    @foreach ($arcs as $arc)
        @unless ($arc['full'])
            <circle cx="{{ $arc['to'][0] }}" cy="{{ $arc['to'][1] }}" r="{{ $strokeWidth / 2 }}" fill="{{ $arc['color'] }}" />
        @endunless
    @endforeach
</svg>
