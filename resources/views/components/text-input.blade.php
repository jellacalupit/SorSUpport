@props(['disabled' => false])

<input @disabled($disabled) {{ $attributes->merge(['class' => 'block w-full px-3 py-2 border border-input bg-card text-foreground placeholder-muted-foreground rounded-lg shadow-panel focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring transition ease-in-out duration-150 disabled:opacity-50 disabled:cursor-not-allowed']) }}>
