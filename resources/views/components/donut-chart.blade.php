@props([
    'title',
    'segments' => [], // each: ['label' => ..., 'color' => ..., 'count' => ...]
    'totalLabel' => 'Tickets',
    // Stack title, ring and legend on phones, for cards that sit two to a row there.
    'stack' => false,
])

@php $total = array_sum(array_column($segments, 'count')); @endphp

<div {{ $attributes->merge(['class' => 'flex min-w-0 items-center rounded-[20px] border border-border bg-white shadow-sm ' . ($stack ? 'flex-col gap-2 p-2.5 sm:flex-row sm:gap-3 sm:p-3' : 'gap-3 p-3')]) }}>
    @if ($stack)<h3 class="text-sm font-semibold text-black sm:hidden">{{ $title }}</h3>@endif
    <div class="relative shrink-0 {{ $stack ? 'h-24 w-24' : 'h-28 w-28' }} sm:h-32 sm:w-32">
        <x-donut-ring :segments="$segments" class="drop-shadow-sm" />
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
