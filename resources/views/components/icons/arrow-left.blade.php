@props(['class' => 'h-4 w-4', 'strokeWidth' => 2])

<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="{{ $strokeWidth }}" stroke-linecap="round" stroke-linejoin="round" {{ $attributes->merge(['class' => $class]) }}>
    <line x1="19" y1="12" x2="5" y2="12" />
    <polyline points="12 5 5 12 12 19" />
</svg>
