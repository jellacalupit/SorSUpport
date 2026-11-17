@props(['user' => null, 'label' => null])

<span class="relative inline-block min-w-0">
    @if ($user)
        <button type="button" class="max-w-full truncate text-left hover:text-primary hover:underline" aria-label="View {{ $label ?? $user->table_name }} profile">{{ $label ?? $user->table_name }}</button>
        <span data-keep-in-view class="brand-gradient w-72 rounded-xl p-4 text-left text-primary-foreground shadow-lg">
            <span class="flex items-center gap-3">
                <span class="grid h-12 w-12 shrink-0 place-items-center overflow-hidden rounded-full bg-primary-foreground/15 text-sm font-bold ring-2 ring-primary-foreground/30">
                    @if ($user->avatar_path)
                        <img src="{{ asset('storage/' . $user->avatar_path) }}" alt="" class="h-full w-full object-cover">
                    @else
                        {{ $user->name_initials }}
                    @endif
                </span>
                <span class="min-w-0">
                    <span class="block wrap-break-word text-sm font-bold">{{ $user->table_name }}</span>
                    <span class="mt-1 block wrap-break-word text-[11px] leading-relaxed">
                        @if ($user->role === \App\Models\User::ROLE_STUDENT)
                            ID {{ $user->student?->student_id ?? 'N/A' }} · {{ $user->student?->college ?? 'College not specified' }} · {{ $user->student?->program ?? 'Program not specified' }}
                        @else
                            ID {{ $user->recipient?->staff_id ?? $user->username ?? $user->id }} · {{ $user->recipient?->unit ?? 'College or office not specified' }} · {{ $user->recipient?->designation ?? 'Designation not specified' }}
                        @endif
                    </span>
                </span>
            </span>
            <span class="mt-3 inline-flex rounded-full border border-primary-foreground/40 px-3 py-1 text-[10px] font-semibold">{{ $user->role === \App\Models\User::ROLE_STUDENT ? 'Student' : ($user->isSdsAdmin() ? 'Admin' : 'Recipient') }}</span>
        </span>
    @else
        {{ $label ?? 'Unknown' }}
    @endif
</span>
