@props([
    'name',
    // Each option: ['value' => ..., 'label' => ..., 'detail' => optional second line, 'group' => optional heading].
    'options' => [],
    'selected' => null,
    'placeholder' => 'Select an option',
    'required' => false,
    'id' => null,
    'label' => null,
])

@php
    $options = collect($options)->map(fn ($option) => [
        'value' => (string) $option['value'],
        'label' => (string) $option['label'],
        'detail' => $option['detail'] ?? null,
        'group' => $option['group'] ?? null,
    ])->values();
    $selected = $selected === null ? '' : (string) $selected;
@endphp

{{-- The system's dropdown: a rounded trigger and a popover list. The choice is sent through a
     visually hidden field so a required picker still stops the form until something is chosen. --}}
<div x-data="{ value: @js($selected), options: @js($options), get chosen() { return this.options.find((option) => option.value === this.value) ?? null; } }" {{ $attributes->merge(['class' => 'relative min-w-0']) }}>
    <details class="group relative" x-on:click.outside="$el.removeAttribute('open')">
        <summary @if ($id) id="{{ $id }}" @endif @if ($label) aria-label="{{ $label }}" @endif class="flex min-h-9 w-full cursor-pointer list-none items-center justify-between gap-2 rounded-lg border border-input bg-muted px-3 py-1.5 text-left text-xs font-normal normal-case tracking-normal outline-none transition-colors hover:bg-accent focus-visible:ring-1 focus-visible:ring-ring [&::-webkit-details-marker]:hidden">
            <span class="min-w-0 truncate" x-bind:class="chosen && chosen.value !== '' ? 'text-foreground' : 'text-muted-foreground'" x-text="chosen ? chosen.label : @js($placeholder)">{{ $options->firstWhere('value', $selected)['label'] ?? $placeholder }}</span>
            <svg class="h-4 w-4 shrink-0 opacity-50 transition-transform group-open:rotate-180" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m6 9 6 6 6-6" /></svg>
        </summary>
        <div class="absolute top-full right-0 left-0 z-50 mt-1 max-h-64 overflow-y-auto rounded-lg border bg-popover p-1 text-popover-foreground shadow-lg">
            @foreach ($options as $index => $option)
                @if ($option['group'] && ($index === 0 || $options[$index - 1]['group'] !== $option['group']))
                    <p class="px-3 pt-2 pb-1 text-[10px] font-semibold tracking-wide text-muted-foreground uppercase">{{ $option['group'] }}</p>
                @endif
                <button type="button" class="flex w-full items-center rounded-md px-3 py-2 text-left text-xs normal-case tracking-normal hover:bg-accent hover:text-accent-foreground" x-bind:class="value === @js($option['value']) ? 'bg-primary-soft text-primary' : 'text-foreground'" x-on:click="value = @js($option['value']); $dispatch('picked', value); $el.closest('details').removeAttribute('open')">
                    <span class="min-w-0">
                        <span class="block wrap-break-word font-medium">{{ $option['label'] }}</span>
                        @if ($option['detail'])
                            <span class="block wrap-break-word text-[11px] font-normal text-muted-foreground">{{ $option['detail'] }}</span>
                        @endif
                    </span>
                </button>
            @endforeach
        </div>
    </details>
    <input type="text" name="{{ $name }}" value="{{ $selected }}" x-bind:value="value" @required($required) tabindex="-1" aria-hidden="true" class="pointer-events-none absolute bottom-0 left-1/2 h-px w-px opacity-0">
</div>
