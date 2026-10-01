@props(['class' => 'h-4 w-4', 'strokeWidth' => 2])
<svg {{ $attributes->merge(['class' => $class]) }} fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="{{ $strokeWidth }}">
    <path stroke-linecap="round" stroke-linejoin="round" d="m12 14 4-4" />
    <path stroke-linecap="round" stroke-linejoin="round" d="M3.34 19a10 10 0 1 1 17.32 0" />
</svg>
