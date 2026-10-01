@props(['class' => 'h-4 w-4', 'strokeWidth' => 2])
<svg {{ $attributes->merge(['class' => $class]) }} fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="{{ $strokeWidth }}">
    <path stroke-linecap="round" stroke-linejoin="round" d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4" />
    <path stroke-linecap="round" stroke-linejoin="round" d="m16 17 5-5-5-5M21 12H9" />
</svg>
