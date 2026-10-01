@props(['class' => 'h-4 w-4', 'strokeWidth' => 2])
<svg {{ $attributes->merge(['class' => $class]) }} fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="{{ $strokeWidth }}">
    <path stroke-linecap="round" stroke-linejoin="round" d="M21.2 8.4c.5.38.8.97.8 1.6v10a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V10a2 2 0 0 1 .8-1.6l8-6a2 2 0 0 1 2.4 0l8 6Z" />
    <path stroke-linecap="round" stroke-linejoin="round" d="m22 10-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 10" />
</svg>
