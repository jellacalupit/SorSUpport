@props([
    'title' => null,
    'action' => null,
    'subtleTitle' => false,
    'class' => '',
])

<section @class(['surface min-w-0 max-w-full p-4 sm:p-6', $class])>
    @if($title || $action)
        <div class="mb-4 grid min-w-0 max-w-full grid-cols-[minmax(0,1fr)_auto] items-center gap-3">
            <h2 @class([
                'truncate',
                'text-sm font-medium text-muted-foreground' => $subtleTitle,
                'font-display text-base font-bold sm:text-lg' => !$subtleTitle,
            ])>
                {{ $title }}
            </h2>
            @if($action)
                <div class="min-w-0 max-w-full">{{ $action }}</div>
            @endif
        </div>
    @endif
    
    {{ $slot }}
</section>
