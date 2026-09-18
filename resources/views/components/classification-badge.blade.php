@props(['classification', 'class' => ''])

@php
    $styles = [
        'needs_resolution' => [
            'label' => 'Needs Resolution',
            'className' => 'bg-primary-soft text-primary',
        ],
        'informational' => [
            'label' => 'Informational',
            'className' => 'bg-gray-200 text-gray-600',
        ],
        'invalid' => [
            'label' => 'Invalid',
            'className' => 'bg-destructive/10 text-destructive',
        ],
        'none' => [
            'label' => '—',
            'className' => 'border-0 bg-transparent px-0 text-muted-foreground',
        ],
    ];
    
    $style = $styles[$classification] ?? $styles['none'];
@endphp

<span {{ $attributes->merge(['class' => "inline-flex min-h-[1.375rem] items-center " . ($classification === 'informational' ? 'rounded-full' : 'rounded-md') . " px-2 py-0.5 text-xs font-semibold {$style['className']} {$class}"]) }}>
    {{ $style['label'] }}
</span>
