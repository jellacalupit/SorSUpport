@extends('layouts.auth')

@section('content')
<div class="grid min-h-screen place-items-center bg-sidebar px-5 py-12">
    <div class="relative w-full max-w-md rounded-2xl bg-card p-6 text-center shadow-lg sm:p-8">
        <a href="{{ route('login') }}" aria-label="Close" class="absolute top-4 right-4 text-muted-foreground">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="h-5 w-5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/>
            </svg>
        </a>

        <span class="mx-auto grid h-20 w-20 place-items-center rounded-full bg-primary text-primary-foreground">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="h-8 w-8">
                <rect width="20" height="16" x="2" y="4" rx="2"/>
                <path stroke-linecap="round" stroke-linejoin="round" d="m22 7-8.97 5.7a2 2 0 0 1-2.06 0L2 7"/>
            </svg>
        </span>

        <h1 class="mt-5 font-display text-xl font-bold sm:text-2xl">Verify Your Email</h1>
        <p class="mt-3 text-xs text-muted-foreground sm:text-sm">
            An email notification has been sent to
            <br />
            <span class="font-bold text-primary">{{ $maskedEmail }}</span>
        </p>
        <p class="mt-3 text-xs text-muted-foreground sm:text-sm">Please verify ownership of this account before proceeding.</p>

        <div class="mt-6 flex items-start gap-2.5 rounded-xl bg-muted p-3 text-left text-xs text-muted-foreground sm:text-sm">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="mt-0.5 h-4 w-4 shrink-0">
                <circle cx="12" cy="12" r="9"/>
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v5M12 16h.01"/>
            </svg>
            <p>Check your inbox and click the verification link sent to your email.</p>
        </div>

        <div class="mt-5 text-[11px] text-muted-foreground sm:text-xs">
            <span>Didn&apos;t receive an email?</span>
            <form method="POST" action="{{ route('verification.send') }}" class="inline">
                @csrf
                <button type="submit" class="font-semibold text-primary underline">Resend</button>
            </form>
        </div>
    </div>
</div>
@endsection
