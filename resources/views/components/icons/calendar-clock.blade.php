@props(['class' => 'h-4 w-4', 'strokeWidth' => 2])

<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="{{ $strokeWidth }}" stroke-linecap="round" stroke-linejoin="round" {{ $attributes->merge(['class' => $class]) }}>
    <rect x="3" y="4" width="18" height="18" rx="2" ry="2" />
    <line x1="16" y1="2" x2="16" y2="6" />
    <line x1="8" y1="2" x2="8" y2="6" />
    <line x1="3" y1="10" x2="21" y2="10" />
    <circle cx="12" cy="14" r="2" />
    <line x1="12" y1="13" x2="12" y2="15" />
    <line x1="11" y1="14" x2="13" y2="14" />
</svg>
