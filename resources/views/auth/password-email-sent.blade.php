@extends('layouts.auth')

@section('content')
<div class="grid min-h-screen place-items-center bg-sidebar px-5 py-12">
    <div class="relative w-full max-w-md rounded-2xl bg-card p-6 text-center shadow-lg sm:p-8">
        <button
            type="button"
            onclick="window.location.href='{{ route('login') }}'"
            aria-label="Close"
            class="absolute top-4 right-4 text-muted-foreground"
        >
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="h-5 w-5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/>
            </svg>
        </button>

        <span class="mx-auto grid h-16 w-16 place-items-center rounded-full bg-primary text-primary-foreground">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="h-9 w-9">
                <rect width="20" height="16" x="2" y="4" rx="2"/>
                <path stroke-linecap="round" stroke-linejoin="round" d="m22 7-8.97 5.7a2 2 0 0 1-2.06 0L2 7"/>
            </svg>
        </span>

        <div class="mt-4 text-center">
            <h1 class="text-2xl font-bold text-foreground">Email Sent</h1>
            <p class="mt-3 text-sm text-muted-foreground">
                A password reset link has been sent to
                <br>
                <span class="font-bold text-primary">{{ session('reset_email', 'your registered email') }}</span>
            </p>
            <p class="mt-3 text-sm text-muted-foreground">Open the link in your inbox to create a new password.</p>
        </div>

        <div class="mt-6 flex items-start gap-2.5 rounded-xl bg-muted p-3 text-left text-sm text-muted-foreground">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="mt-0.5 h-4 w-4 shrink-0">
                <circle cx="12" cy="12" r="9"/>
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v5M12 16h.01"/>
            </svg>
            <p>The reset link expires in 15 minutes for your security.</p>
        </div>

        <a href="{{ route('login') }}" class="mt-6 inline-flex h-12 w-full items-center justify-center rounded-full bg-primary px-4 py-2 text-base font-medium text-primary-foreground transition-colors hover:bg-primary/90">
            Back to Login
        </a>
    </div>
</div>
@endsection
