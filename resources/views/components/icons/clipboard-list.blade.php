@props(['class' => 'h-4 w-4', 'strokeWidth' => 2])
<svg {{ $attributes->merge(['class' => $class]) }} fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="{{ $strokeWidth }}">
    <rect width="8" height="4" x="8" y="2" rx="1" ry="1" />
    <path stroke-linecap="round" stroke-linejoin="round" d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2" />
    <path stroke-linecap="round" stroke-linejoin="round" d="M8 10h8M8 14h8M8 18h5" />
</svg>
