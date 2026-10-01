@props(['class' => 'h-4 w-4', 'strokeWidth' => 2])
<svg {{ $attributes->merge(['class' => $class]) }} fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="{{ $strokeWidth }}">
    <circle cx="11" cy="11" r="7" />
    <path stroke-linecap="round" stroke-linejoin="round" d="m20 20-4-4" />
</svg>
