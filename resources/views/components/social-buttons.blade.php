{{-- 
=============================================================================
KOMPONEN: TOMBOL SOCIAL AUTH — Google & GitHub (2 kolom sebelahan)
Package: mixudev/laravel-authentication
Deskripsi: Google & GitHub berdampingan (grid 2 kolom), styling via Tailwind langsung.
=============================================================================
--}}
@php
    $socialConfig    = config('authentication.features.social', []);
    $isSocialEnabled = $socialConfig['enabled'] ?? false;
    $providers       = $socialConfig['providers'] ?? [];

    $googleEnabled = $isSocialEnabled && ($providers['google']['enabled'] ?? false);
    $githubEnabled = $isSocialEnabled && ($providers['github']['enabled'] ?? false);

    $googleUrl = Route::has('social.redirect')
        ? route('social.redirect', ['provider' => 'google'])
        : (Route::has('authentication.social.redirect') ? route('authentication.social.redirect', ['provider' => 'google']) : url('/auth/google/redirect'));

    $githubUrl = Route::has('social.redirect')
        ? route('social.redirect', ['provider' => 'github'])
        : (Route::has('authentication.social.redirect') ? route('authentication.social.redirect', ['provider' => 'github']) : url('/auth/github/redirect'));

    $btnBase = 'flex items-center justify-center gap-2 w-full rounded-lg border px-4 py-2.5 text-sm font-medium transition-all duration-150 cursor-pointer no-underline select-none focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-500 focus-visible:ring-offset-1 hover:-translate-y-px active:translate-y-0 active:opacity-80';
    $btnLight = 'bg-white border-gray-300 text-gray-800 hover:bg-gray-50 hover:border-gray-400 dark:bg-zinc-900 dark:border-zinc-600 dark:text-zinc-100 dark:hover:bg-zinc-800 dark:hover:border-zinc-500';
@endphp

@if ($googleEnabled || $githubEnabled)

    @if ($googleEnabled && $githubEnabled)
        {{-- Kedua aktif: 2 kolom sebelahan --}}
        <div class="grid grid-cols-2 gap-2.5">

            {{-- Google --}}
            <a href="{{ $googleUrl }}"
               class="{{ $btnBase }} {{ $btnLight }}"
               aria-label="Sign in with Google">
                <svg width="16" height="16" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" style="flex-shrink:0">
                    <path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z" fill="#4285F4"/>
                    <path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" fill="#34A853"/>
                    <path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l3.66-2.84z" fill="#FBBC05"/>
                    <path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z" fill="#EA4335"/>
                </svg>
                <span>Google</span>
            </a>

            {{-- GitHub --}}
            <a href="{{ $githubUrl }}"
               class="{{ $btnBase }} {{ $btnLight }}"
               aria-label="Sign in with GitHub">
                <svg width="16" height="16" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" fill="currentColor" style="flex-shrink:0">
                    <path fill-rule="evenodd" clip-rule="evenodd" d="M12 2C6.477 2 2 6.484 2 12.017c0 4.425 2.865 8.18 6.839 9.504.5.092.682-.217.682-.483 0-.237-.008-.868-.013-1.703-2.782.605-3.369-1.343-3.369-1.343-.454-1.158-1.11-1.466-1.11-1.466-.908-.62.069-.608.069-.608 1.003.07 1.53 1.032 1.53 1.032.892 1.53 2.341 1.088 2.91.832.092-.647.35-1.088.636-1.338-2.22-.253-4.555-1.113-4.555-4.951 0-1.093.39-1.988 1.029-2.688-.103-.253-.446-1.272.098-2.65 0 0 .84-.27 2.75 1.026A9.564 9.564 0 0112 6.844c.85.004 1.705.115 2.504.337 1.909-1.296 2.747-1.027 2.747-1.027.546 1.379.202 2.398.1 2.651.64.7 1.028 1.595 1.028 2.688 0 3.848-2.339 4.695-4.566 4.943.359.309.678.92.678 1.855 0 1.338-.012 2.419-.012 2.747 0 .268.18.58.688.482A10.019 10.019 0 0022 12.017C22 6.484 17.522 2 12 2z"/>
                </svg>
                <span>GitHub</span>
            </a>

        </div>

    @elseif ($googleEnabled)
        <a href="{{ $googleUrl }}"
           class="{{ $btnBase }} {{ $btnLight }}"
           aria-label="Sign in with Google">
            <svg width="16" height="16" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" style="flex-shrink:0">
                <path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z" fill="#4285F4"/>
                <path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" fill="#34A853"/>
                <path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l3.66-2.84z" fill="#FBBC05"/>
                <path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z" fill="#EA4335"/>
            </svg>
            <span>Google</span>
        </a>

    @elseif ($githubEnabled)
        <a href="{{ $githubUrl }}"
           class="{{ $btnBase }} {{ $btnLight }}"
           aria-label="Sign in with GitHub">
            <svg width="16" height="16" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" fill="currentColor" style="flex-shrink:0">
                <path fill-rule="evenodd" clip-rule="evenodd" d="M12 2C6.477 2 2 6.484 2 12.017c0 4.425 2.865 8.18 6.839 9.504.5.092.682-.217.682-.483 0-.237-.008-.868-.013-1.703-2.782.605-3.369-1.343-3.369-1.343-.454-1.158-1.11-1.466-1.11-1.466-.908-.62.069-.608.069-.608 1.003.07 1.53 1.032 1.53 1.032.892 1.53 2.341 1.088 2.91.832.092-.647.35-1.088.636-1.338-2.22-.253-4.555-1.113-4.555-4.951 0-1.093.39-1.988 1.029-2.688-.103-.253-.446-1.272.098-2.65 0 0 .84-.27 2.75 1.026A9.564 9.564 0 0112 6.844c.85.004 1.705.115 2.504.337 1.909-1.296 2.747-1.027 2.747-1.027.546 1.379.202 2.398.1 2.651.64.7 1.028 1.595 1.028 2.688 0 3.848-2.339 4.695-4.566 4.943.359.309.678.92.678 1.855 0 1.338-.012 2.419-.012 2.747 0 .268.18.58.688.482A10.019 10.019 0 0022 12.017C22 6.484 17.522 2 12 2z"/>
            </svg>
            <span>GitHub</span>
        </a>
    @endif

@endif
