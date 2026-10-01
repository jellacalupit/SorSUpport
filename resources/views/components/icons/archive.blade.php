@props(['class' => 'h-4 w-4', 'strokeWidth' => 2])
<svg {{ $attributes->merge(['class' => $class]) }} fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="{{ $strokeWidth }}" stroke-linecap="round" stroke-linejoin="round">
    <path d="M4 7h16v13H4z" />
    <path d="M3 4h18v3H3zM8 11h8" />
</svg>
