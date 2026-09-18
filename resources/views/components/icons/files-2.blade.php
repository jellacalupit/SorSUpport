@props(['class' => 'h-4 w-4', 'strokeWidth' => 2])
<svg {{ $attributes->merge(['class' => $class]) }} fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="{{ $strokeWidth }}" stroke-linecap="round" stroke-linejoin="round">
    <path d="M8 3h8l4 4v10a2 2 0 0 1-2 2h-2" />
    <path d="M16 3v5h4" />
    <path d="M4 7h8l4 4v8a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V9a2 2 0 0 1 2-2Z" />
    <path d="M12 7v5h4M7 16h5" />
</svg>
