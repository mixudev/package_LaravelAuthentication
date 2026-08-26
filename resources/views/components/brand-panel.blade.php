{{-- 
=============================================================================
KOMPONEN: BRAND PANEL (SIDEBAR GRAFIS CONSOLE)
Package: mixudev/laravel-authentication
Deskripsi: Komponen panel kiri yang menampilkan identitas brand, status TLS / live monitor,
           ringkasan fitur keamanan, dan watermark sistem.
=============================================================================
--}}
@props([
    'title' => null,
    'subtitle' => null,
    'statusBadge' => null,
])

<div class="relative z-10 flex flex-col justify-between h-full space-y-12">
    
    {{-- Bagian Atas: Logo & Badge Live Status --}}
    <div class="space-y-8">
        <div class="flex items-center space-x-3">
            <div class="w-10 h-10 rounded-xl bg-gradient-to-tr from-amber-500 to-amber-300 p-0.5 shadow-lg shadow-amber-500/20 flex items-center justify-center text-slate-950 font-bold">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                </svg>
            </div>
            <div>
                <span class="font-heading font-bold text-xl tracking-tight text-white block">
                    {{ config('authentication.ui.brand_name', config('app.name', 'Console Auth')) }}
                </span>
                <span class="text-[11px] font-mono-code text-slate-400 block tracking-wider uppercase">
                    Security Gateway
                </span>
            </div>
        </div>

        {{-- Badge Status Operasional --}}
        <div class="inline-flex items-center gap-2.5 px-3 py-1.5 rounded-full text-xs font-mono-code bg-slate-900/90 border border-slate-800 text-slate-300 shadow-inner">
            <span class="relative flex h-2.5 w-2.5">
                <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                <span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-emerald-500"></span>
            </span>
            <span>{{ $statusBadge ?? config('authentication.ui.brand_badge', 'SYSTEM LIVE // TLS 1.3 ACTIVE') }}</span>
        </div>

        {{-- Headline & Penjelasan --}}
        <div class="space-y-4 pt-2">
            <h2 class="font-heading font-bold text-3xl xl:text-4xl text-white tracking-tight leading-tight">
                {{ $title ?? config('authentication.ui.brand_tagline', 'Enterprise Security & Identity Access Management') }}
            </h2>
            <p class="text-slate-400 text-sm xl:text-base leading-relaxed max-w-sm">
                {{ $subtitle ?? 'Akses portal terenkripsi berstandar Zero-Trust dengan proteksi mitigasi brute-force dan audit logging otomatis.' }}
            </p>
        </div>
    </div>

    {{-- Bagian Tengah: Visual Monitor / Metrics Telemetri --}}
    <div class="p-5 rounded-2xl bg-slate-900/60 border border-slate-800/80 backdrop-blur-sm space-y-3">
        <div class="flex items-center justify-between text-xs font-mono-code text-slate-400 border-b border-slate-800/80 pb-2.5">
            <span class="flex items-center gap-2">
                <span class="w-1.5 h-1.5 rounded-full bg-amber-400"></span>
                SECURITY TELEMETRY
            </span>
            <span class="text-emerald-400 font-semibold">ALL SYSTEMS NOMINAL</span>
        </div>
        <div class="grid grid-cols-2 gap-3 text-xs font-mono-code pt-1">
            <div class="bg-slate-950/60 p-2.5 rounded-lg border border-slate-850">
                <span class="text-slate-500 block text-[10px]">ENCRYPTION</span>
                <span class="text-slate-200 font-medium">AES-256 / ARGON2</span>
            </div>
            <div class="bg-slate-950/60 p-2.5 rounded-lg border border-slate-850">
                <span class="text-slate-500 block text-[10px]">THROTTLE SHIELD</span>
                <span class="text-slate-200 font-medium">COMPOSITE IP+ID</span>
            </div>
        </div>
    </div>

    {{-- Bagian Bawah: Copyright & Versioning --}}
    <div class="flex items-center justify-between text-xs font-mono-code text-slate-500 pt-4 border-t border-slate-800/60">
        <span>&copy; {{ date('Y') }} {{ config('app.name', 'Laravel') }}</span>
        <span class="text-slate-400 font-medium">v1.2.0-STABLE</span>
    </div>

</div>
