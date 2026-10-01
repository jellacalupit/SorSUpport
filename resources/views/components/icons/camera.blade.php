@props(['class' => 'h-4 w-4', 'strokeWidth' => 2])
<svg {{ $attributes->merge(['class' => $class]) }} fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="{{ $strokeWidth }}" stroke-linecap="round" stroke-linejoin="round">
    <path d="M14.5 4h-5L8 7H5a2 2 0 0 0-2 2v9a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2h-3l-1.5-3Z" />
    <circle cx="12" cy="13" r="3" />
</svg>
