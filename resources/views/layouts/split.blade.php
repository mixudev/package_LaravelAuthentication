{{-- 
=============================================================================
TEMPLATE 1: 2-COLUMN SPLIT LAYOUT (DEEP DARK ZINC & CLEAN)
Package: mixudev/laravel-authentication
Deskripsi: Layout 2 kolom modern dengan panel branding di kiri dan form di kanan.
=============================================================================
--}}
@props([
    'title' => null,
    'brandTitle' => null,
    'brandSubtitle' => null,
    'statusBadge' => null,
])

@php
    $brandName = config('authentication.ui.brand_name', config('app.name', 'Laravel'));
    $logoUrl = config('authentication.ui.logo_url');
    $hasFaviconSvg = file_exists(public_path('favicon.svg'));
    $hasFaviconIco = file_exists(public_path('favicon.ico'));
@endphp

<x-authentication::layouts.auth :title="$title">
    <div class="min-h-screen w-full grid grid-cols-1 lg:grid-cols-12 bg-zinc-50 dark:bg-zinc-950">
        
        {{-- Sisi Kiri: Panel Branding Minimalis (Desktop Only) --}}
        <aside class="lg:col-span-5 relative hidden lg:flex flex-col justify-between bg-zinc-950 text-zinc-100 p-12 xl:p-16 border-r border-zinc-800">
            <x-authentication::brand-panel 
                :title="$brandTitle"
                :subtitle="$brandSubtitle"
                :statusBadge="$statusBadge"
            />
        </aside>

        {{-- Sisi Kanan: Area Formulir Autentikasi --}}
        <main class="lg:col-span-7 flex flex-col justify-center items-center px-6 py-10 sm:px-12 bg-white dark:bg-zinc-950">
            <div class="w-full max-w-md mx-auto space-y-6">
                
                {{-- Logo Mobile --}}
                <div class="lg:hidden flex items-center justify-between pb-4 border-b border-zinc-200 dark:border-zinc-800">
                    <div class="flex items-center space-x-3">
                        @if ($logoUrl)
                            <img src="{{ $logoUrl }}" alt="{{ $brandName }}" class="w-8 h-8 object-contain rounded-lg">
                        @elseif ($hasFaviconSvg)
                            <img src="{{ asset('favicon.svg') }}" alt="{{ $brandName }}" class="w-8 h-8 object-contain">
                        @elseif ($hasFaviconIco)
                            <img src="{{ asset('favicon.ico') }}" alt="{{ $brandName }}" class="w-8 h-8 object-contain">
                        @else
                            <div class="w-8 h-8 rounded-lg bg-zinc-900 dark:bg-zinc-100 flex items-center justify-center text-white dark:text-zinc-950 font-bold">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                                </svg>
                            </div>
                        @endif
                        <span class="font-semibold text-base text-zinc-900 dark:text-zinc-100">
                            {{ $brandName }}
                        </span>
                    </div>
                </div>

                {{-- Slot Konten Formulir Halaman --}}
                {{ $slot }}
            </div>
        </main>

    </div>
</x-authentication::layouts.auth>
