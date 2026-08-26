{{-- 
=============================================================================
TEMPLATE 2: CENTERED CLEAN CARD LAYOUT (LARAVEL BREEZE STYLE)
Package: mixudev/laravel-authentication
Deskripsi: Layout kartu terpusat bersih, minimalis, dan elegan standar Laravel.
=============================================================================
--}}
@props([
    'title' => null,
])

<x-authentication::layouts.auth :title="$title">
    <div class="min-h-screen flex flex-col sm:justify-center items-center pt-6 sm:pt-0 bg-gray-100 dark:bg-gray-900 px-4">
        
        {{-- Logo Brand Minimalis di Bagian Atas --}}
        <div class="mb-4">
            <a href="/" class="flex items-center gap-2">
                <div class="w-10 h-10 rounded-lg bg-gray-900 dark:bg-gray-100 flex items-center justify-center text-white dark:text-gray-900 shadow-sm">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                    </svg>
                </div>
            </a>
        </div>

        {{-- Kartu Utama Bersih --}}
        <div class="w-full sm:max-w-md bg-white dark:bg-gray-800 shadow-md overflow-hidden sm:rounded-lg p-6 sm:p-8">
            {{ $slot }}
        </div>

    </div>
</x-authentication::layouts.auth>
