@props(['class' => 'h-4 w-4', 'strokeWidth' => 2])
<svg {{ $attributes->merge(['class' => $class]) }} fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="{{ $strokeWidth }}">
    <path stroke-linecap="round" stroke-linejoin="round" d="M3 3v18h18" />
    <path stroke-linecap="round" stroke-linejoin="round" d="M7 16v-3M12 16V8M17 16V5" />
</svg>
