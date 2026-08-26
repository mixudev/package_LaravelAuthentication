{{-- 
=============================================================================
KOMPONEN: BRAND PANEL (SIDEBAR KIRI MINIMALIS)
Package: mixudev/laravel-authentication
Deskripsi: Panel kiri simpel, bersih, dan elegan menampilkan identitas brand,
           status sistem aktif, dan ringkasan jaminan keamanan ZTA.
=============================================================================
--}}
@props([
    'title' => null,
    'subtitle' => null,
    'statusBadge' => null,
])

<div class="relative z-10 flex flex-col justify-between h-full space-y-10">
    
    {{-- Bagian Atas: Logo & Headline Bersih --}}
    <div class="space-y-6">
        {{-- Logo Brand --}}
        <div class="flex items-center space-x-3">
            <div class="w-9 h-9 rounded-xl bg-gradient-to-tr from-amber-500 to-amber-400 p-0.5 shadow-md flex items-center justify-center text-slate-950 font-bold">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                </svg>
            </div>
            <div>
                <span class="font-heading font-bold text-lg tracking-tight text-white block">
                    {{ config('authentication.ui.brand_name', config('app.name', 'Console Auth')) }}
                </span>
            </div>
        </div>

        {{-- Badge Status Ringkas --}}
        <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full text-xs font-mono-code bg-slate-800/80 border border-slate-700/80 text-slate-300">
            <span class="relative flex h-2 w-2">
                <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                <span class="relative inline-flex rounded-full h-2 w-2 bg-emerald-500"></span>
            </span>
            <span>{{ $statusBadge ?? config('authentication.ui.brand_badge', 'SYSTEM LIVE // TLS 1.3') }}</span>
        </div>

        {{-- Headline & Deskripsi Ringkas --}}
        <div class="space-y-3 pt-2">
            <h2 class="font-heading font-bold text-2xl xl:text-3xl text-white tracking-tight leading-snug">
                {{ $title ?? config('authentication.ui.brand_tagline', 'Enterprise Security & Identity Gateway') }}
            </h2>
            <p class="text-slate-400 text-sm leading-relaxed max-w-sm">
                {{ $subtitle ?? 'Portal autentikasi terenkripsi dengan proteksi brute-force otomatis dan standar Zero-Trust.' }}
            </p>
        </div>
    </div>

    {{-- Bagian Tengah: Fitur Keamanan Ringkas (Minimalis) --}}
    <div class="space-y-3 pt-2">
        <div class="flex items-center gap-3 text-xs text-slate-300 font-medium">
            <div class="w-5 h-5 rounded-md bg-amber-500/10 text-amber-400 flex items-center justify-center flex-shrink-0">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                </svg>
            </div>
            <span>Enkripsi End-to-End &amp; Argon2id Hash</span>
        </div>
        <div class="flex items-center gap-3 text-xs text-slate-300 font-medium">
            <div class="w-5 h-5 rounded-md bg-amber-500/10 text-amber-400 flex items-center justify-center flex-shrink-0">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                </svg>
            </div>
            <span>Composite Rate Limiting &amp; Anti Brute-Force</span>
        </div>
        <div class="flex items-center gap-3 text-xs text-slate-300 font-medium">
            <div class="w-5 h-5 rounded-md bg-amber-500/10 text-amber-400 flex items-center justify-center flex-shrink-0">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                </svg>
            </div>
            <span>Mitigasi User Enumeration Terproteksi</span>
        </div>
    </div>

    {{-- Bagian Bawah: Copyright Bersih --}}
    <div class="text-xs font-mono-code text-slate-500 pt-4 border-t border-slate-800">
        <span>&copy; {{ date('Y') }} {{ config('app.name', 'Laravel') }} &bull; All rights reserved.</span>
    </div>

</div>
