@props(['status'])

@if ($status)
    <div {{ $attributes->merge(['class' => 'font-medium text-sm text-success bg-success/10 border border-success/30 rounded-lg px-4 py-3']) }}>
        {{ $status }}
    </div>
@endif
