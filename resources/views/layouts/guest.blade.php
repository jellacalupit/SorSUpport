@props(['maxWidth' => 'max-w-md', 'showFooter' => true, 'roundedHeader' => false])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <link rel="icon" href="{{ asset('branding/sorsu logo.png') }}" type="image/png">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'SorSUpport') }}</title>

        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Bitter:wght@600;700;800&family=Source+Sans+3:wght@400;500;600;700&display=swap" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans text-foreground antialiased bg-background">
        <div class="flex min-h-screen flex-col bg-background">
            <div class="{{ $roundedHeader ? 'md:flex md:flex-1' : '' }}">
                <header class="brand-gradient px-6 pt-12 pb-12 text-center text-primary-foreground sm:pt-16{{ $roundedHeader ? ' login-header-curve md:flex md:min-h-screen md:flex-[2] md:flex-col md:justify-center' : '' }}">
                    <img src="{{ asset('branding/sorsu logo.png') }}" alt="SorSUpport logo" class="mx-auto h-24 w-24 rounded-full object-contain sm:h-28 sm:w-28">
                    <h1 class="mt-5 font-display text-2xl font-bold sm:text-3xl lg:text-4xl">SorSUpport</h1>
                    <p class="mt-2 text-xs opacity-85 sm:text-sm lg:text-base">Student Complaint and Ticketing System</p>
                </header>

                <main class="{{ $roundedHeader ? '' : '-mt-6 rounded-t-3xl ' }}flex-1 bg-background px-6 py-8 lg:px-10{{ $roundedHeader ? ' md:flex md:min-h-screen md:flex-[3] md:items-center md:justify-center' : '' }}">
                    <div class="mx-auto w-full {{ $maxWidth }}">
                        <div class="rounded-2xl border border-border bg-card p-6 shadow-sm">
                            {{ $slot }}
                        </div>
                    </div>
                </main>
            </div>

            @if($showFooter)
                <footer class="px-6 py-8 text-center text-xs text-muted-foreground">
                    © 2026 SorSU - Bulan Campus. All rights reserved.
                </footer>
            @endif
        </div>
    </body>
</html>
