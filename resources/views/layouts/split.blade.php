{{-- 
=============================================================================
TEMPLATE 1: 2-COLUMN SPLIT CONSOLE LAYOUT
Package: mixudev/laravel-authentication
Deskripsi: Layout 2 kolom modern (Panel Branding/Monitor di sebelah kiri dan
           Formulir Interaktif di sebelah kanan). Cocok untuk dashboard console enterprise.
=============================================================================
--}}
@props([
    'title' => null,
    'brandTitle' => null,
    'brandSubtitle' => null,
    'statusBadge' => null,
])

<x-authentication::layouts.auth :title="$title">
    <div class="min-h-screen grid grid-cols-1 lg:grid-cols-12 bg-slate-950">
        
        {{-- Sisi Kiri: Panel Branding Grafis (Desktop Only / Responsive) --}}
        <aside class="lg:col-span-5 relative hidden lg:flex flex-col justify-between overflow-hidden bg-gradient-to-br from-slate-900 via-slate-950 to-neutral-950 p-12 xl:p-16 border-r border-slate-800/80">
            {{-- Background Grid Pattern Effect --}}
            <div class="absolute inset-0 bg-[linear-gradient(to_right,#1e293b15_1px,transparent_1px),linear-gradient(to_bottom,#1e293b15_1px,transparent_1px)] bg-[size:32px_32px] [mask-image:radial-gradient(ellipse_60%_50%_at_50%_0%,#000_70%,transparent_100%)] pointer-events-none"></div>
            
            {{-- Ambient Radial Glow --}}
            <div class="absolute -top-24 -left-24 w-96 h-96 bg-amber-500/10 rounded-full blur-3xl pointer-events-none"></div>
            
            <x-authentication::brand-panel 
                :title="$brandTitle"
                :subtitle="$brandSubtitle"
                :statusBadge="$statusBadge"
            />
        </aside>

        {{-- Sisi Kanan: Area Formulir Autentikasi Utama --}}
        <main class="lg:col-span-7 flex flex-col justify-center items-center p-6 sm:p-12 lg:p-16 relative bg-slate-950/60">
            {{-- Ambient Glow Kanan --}}
            <div class="absolute -bottom-20 -right-20 w-80 h-80 bg-cyan-500/5 rounded-full blur-3xl pointer-events-none"></div>

            {{-- Container Form Responsif --}}
            <div class="w-full max-w-md mx-auto relative z-10 space-y-6">
                
                {{-- Logo Mobile (Tampil hanya pada layar kecil saat sidebar brand tersembunyi) --}}
                <div class="lg:hidden flex items-center justify-between pb-4 border-b border-slate-800">
                    <div class="flex items-center space-x-3">
                        <div class="w-9 h-9 rounded-xl bg-gradient-to-tr from-amber-500 to-amber-300 p-0.5 shadow-lg shadow-amber-500/20 flex items-center justify-center text-slate-950 font-bold">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                            </svg>
                        </div>
                        <span class="font-heading font-bold text-lg tracking-tight text-white">
                            {{ config('authentication.ui.brand_name', config('app.name', 'Console Auth')) }}
                        </span>
                    </div>

                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-mono-code bg-slate-900 border border-slate-800 text-slate-400">
                        <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                        TLS 1.3
                    </span>
                </div>

                {{-- Slot Konten Formulir Halaman --}}
                {{ $slot }}

                {{-- Footer Keamanan Standar --}}
                <footer class="pt-6 border-t border-slate-850/60 text-center text-xs text-slate-500">
                    <p class="flex items-center justify-center gap-1.5 font-mono-code text-[11px]">
                        <svg class="w-3.5 h-3.5 text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                        </svg>
                        Encrypted End-to-End &bull; Zero Trust Policy
                    </p>
                </footer>
            </div>
        </main>

    </div>
</x-authentication::layouts.auth>
