{{-- 
=============================================================================
KOMPONEN: BRAND PANEL (SIDEBAR KIRI MINIMALIS)
Package: mixudev/laravel-authentication
Deskripsi: Panel kiri simpel, bersih, dan elegan menampilkan identitas aplikasi.
=============================================================================
--}}
@props([
    'title' => null,
    'subtitle' => null,
    'statusBadge' => null,
])

<div class="flex flex-col justify-between h-full space-y-8">
    
    {{-- Bagian Atas: Logo & Headline --}}
    <div class="space-y-4">
        {{-- Logo Brand --}}
        <div class="flex items-center space-x-3">
            <div class="w-9 h-9 rounded-lg bg-white text-gray-900 flex items-center justify-center font-bold shadow-sm">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                </svg>
            </div>
            <span class="font-bold text-lg text-white">
                {{ config('authentication.ui.brand_name', config('app.name', 'Laravel')) }}
            </span>
        </div>

        {{-- Headline & Deskripsi Simpel --}}
        <div class="space-y-2 pt-6">
            <h2 class="font-semibold text-2xl text-white tracking-tight leading-snug">
                {{ $title ?? config('authentication.ui.brand_tagline', 'Secure Authentication & Access') }}
            </h2>
            <p class="text-gray-400 text-sm leading-relaxed">
                {{ $subtitle ?? 'Portal autentikasi aman, cepat, dan terpercaya untuk seluruh kebutuhan akun Anda.' }}
            </p>
        </div>
    </div>

    {{-- Bagian Bawah: Copyright Bersih --}}
    <div class="text-xs text-gray-500">
        <span>&copy; {{ date('Y') }} {{ config('app.name', 'Laravel') }}. All rights reserved.</span>
    </div>

</div>
