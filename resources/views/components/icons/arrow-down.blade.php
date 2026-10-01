@props(['class' => 'h-4 w-4', 'strokeWidth' => 2])
<svg {{ $attributes->merge(['class' => $class]) }} fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="{{ $strokeWidth }}" stroke-linecap="round" stroke-linejoin="round">
    <path d="m6 15 6-6 6 6" />
</svg>
