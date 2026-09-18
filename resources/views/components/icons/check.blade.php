@props(['class' => 'h-4 w-4', 'strokeWidth' => 2])

<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="{{ $strokeWidth }}" stroke-linecap="round" stroke-linejoin="round" {{ $attributes->merge(['class' => $class]) }}>
    <polyline points="20 6 9 17 4 12" />
</svg>
