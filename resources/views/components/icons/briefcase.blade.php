@props(['class' => 'h-4 w-4', 'strokeWidth' => 2])
<svg {{ $attributes->merge(['class' => $class]) }} fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="{{ $strokeWidth }}" stroke-linecap="round" stroke-linejoin="round">
    <path d="m3 10 9-5 9 5" />
    <path d="M5 10h14M6 10v8M10 10v8M14 10v8M18 10v8" />
    <path d="M4 18h16M3 21h18" />
</svg>
