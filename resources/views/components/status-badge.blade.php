@props(['status', 'classification' => null, 'class' => '', 'showIcon' => true])

@php
    // Map status to Tailwind classes and icon
    $statusStyles = [
        'Submitted' => [
            'className' => 'bg-yellow-100 text-yellow-800',
            'icon' => 'clock',
        ],
        'Needs Clarification' => [
            'className' => 'bg-amber-100 text-amber-800',
            'icon' => 'alert-triangle',
        ],
        'Referred' => [
            'className' => 'bg-purple-100 text-purple-800',
            'icon' => 'loader2',
        ],
        'Assigned' => [
            'className' => 'bg-blue-100 text-blue-800',
            'icon' => 'loader2',
        ],
        'In Progress' => [
            'className' => 'bg-blue-100 text-blue-800',
            'icon' => 'loader2',
        ],
        'Resolved' => [
            'className' => 'bg-green-100 text-green-700',
            'icon' => 'check',
        ],
        'Escalated' => [
            'className' => 'bg-red-100 text-red-700',
            'icon' => 'alert-triangle',
        ],
        'Closed' => [
            'className' => 'bg-gray-200 text-gray-600',
            'icon' => 'lock',
        ],
    ];

    $normalizedStatus = trim((string) $status);
    $normalizedStatus = str_replace(['_', '-'], ' ', $normalizedStatus);
    $normalizedStatus = ucwords($normalizedStatus);
    $style = $statusStyles[$normalizedStatus] ?? $statusStyles['Submitted'];
@endphp

<span {{ $attributes->merge(['class' => "inline-flex shrink-0 items-center gap-1.5 rounded-full border px-2.5 py-0.5 text-xs font-semibold whitespace-nowrap {$style['className']} {$class}"]) }}>
    @if ($showIcon)
        <x-dynamic-component :component="'icons.' . str_replace('_', '-', $style['icon'])" class="h-3 w-3" />
    @endif
    {{ $status }}
</span>
