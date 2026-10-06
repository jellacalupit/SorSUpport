@props([
    'title',
    'segments' => [], // each: ['label' => ..., 'color' => ..., 'count' => ...]
    'totalLabel' => 'Tickets',
    // Stack title, ring and legend on phones, for cards that sit two to a row there.
    'stack' => false,
])

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

<div {{ $attributes->merge(['class' => 'flex min-w-0 items-center rounded-[20px] border border-border bg-white shadow-sm ' . ($stack ? 'flex-col gap-2 p-2.5 sm:flex-row sm:gap-3 sm:p-3' : 'gap-3 p-3')]) }}>
    @if ($stack)<h3 class="text-sm font-semibold text-black sm:hidden">{{ $title }}</h3>@endif
    <div class="relative shrink-0 {{ $stack ? 'h-24 w-24' : 'h-28 w-28' }} sm:h-32 sm:w-32">
        <svg viewBox="0 0 100 100" class="h-full w-full drop-shadow-sm" aria-hidden="true">
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
        <div class="absolute inset-0 flex flex-col items-center justify-center">
            <span class="text-xl font-bold leading-none text-foreground">{{ $total }}</span>
            <span class="mt-0.5 text-[9px] text-muted-foreground">{{ $totalLabel }}</span>
        </div>
    </div>

    <div class="min-w-0 {{ $stack ? 'w-full sm:flex-1' : 'flex-1' }}">
        <h3 class="{{ $stack ? 'hidden sm:block' : '' }} text-sm font-semibold text-black">{{ $title }}</h3>
        <ul class="grid gap-1 text-[11px] text-foreground {{ $stack ? 'sm:mt-1.5' : 'mt-1.5' }}">
            @foreach ($segments as $segment)
                <li class="flex items-center gap-1.5">
                    <span class="h-2.5 w-2.5 shrink-0 rounded-sm" style="background-color: {{ $segment['color'] }};"></span>
                    <span class="min-w-0 flex-1 truncate">{{ $segment['label'] }}</span>
                    <span class="shrink-0 font-semibold tabular-nums">{{ $segment['count'] }}</span>
                </li>
            @endforeach
        </ul>
    </div>
</div>
