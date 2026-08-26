{{-- 
=============================================================================
TEMPLATE 2: CENTERED CLEAN CARD LAYOUT (MODERN BREEZE STYLE)
Package: mixudev/laravel-authentication
Deskripsi: Layout kartu terpusat bersih, modern, dan pekat dengan deteksi favicon otomatis.
=============================================================================
--}}
@props([
    'title' => null,
])

@php
    $brandName = config('authentication.ui.brand_name', config('app.name', 'Laravel'));
    $logoUrl = config('authentication.ui.logo_url');
    $hasFaviconSvg = file_exists(public_path('favicon.svg'));
    $hasFaviconIco = file_exists(public_path('favicon.ico'));
@endphp

<x-authentication::layouts.auth :title="$title">
    <div class="min-h-screen flex flex-col justify-center items-center py-10 sm:py-16 px-4 sm:px-6">
        
        {{-- Logo Aplikasi Menggunakan Favicon Host Otomatis --}}
        <div class="mb-6 flex flex-col items-center">
            <a href="/" class="flex items-center gap-2 group transition-transform duration-150 hover:scale-105">
                @if ($logoUrl)
                    <img src="{{ $logoUrl }}" alt="{{ $brandName }}" class="w-11 h-11 object-contain rounded-xl shadow-xs">
                @elseif ($hasFaviconSvg)
                    <img src="{{ asset('favicon.svg') }}" alt="{{ $brandName }}" class="w-11 h-11 object-contain">
                @elseif ($hasFaviconIco)
                    <img src="{{ asset('favicon.ico') }}" alt="{{ $brandName }}" class="w-11 h-11 object-contain">
                @else
                    <div class="w-11 h-11 rounded-xl bg-zinc-900 dark:bg-zinc-100 flex items-center justify-center text-white dark:text-zinc-950 font-bold shadow-xs">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                        </svg>
                    </div>
                @endif
            </a>
        </div>

        {{-- Kartu Utama Modern & Pekat --}}
        <div class="auth-card w-full sm:max-w-md bg-white dark:bg-zinc-900/90 border border-zinc-200 dark:border-zinc-800 shadow-sm rounded-xl p-6 sm:p-8">
            {{ $slot }}
        </div>

    </div>
</x-authentication::layouts.auth>
