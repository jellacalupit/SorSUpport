@props(['compact' => false])
<div {{ $attributes->merge(['class' => 'flex min-w-0 items-center gap-2.5']) }}>
    <img src="{{ asset('branding/sorsu logo.png') }}" alt="SorSUpport logo" class="h-9 w-9 shrink-0 rounded-lg object-contain">
    @if(!$compact)
        <span class="min-w-0">
            <span class="block font-display text-base leading-tight font-bold">SorSUpport</span>
            <span class="block text-[11px] leading-tight opacity-75">Sorsogon State University – Bulan Campus</span>
        </span>
    @endif
</div>
