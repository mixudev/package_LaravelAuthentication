{{-- 
=============================================================================
TEMPLATE 2: CENTERED MINIMALIST CARD LAYOUT
Package: mixudev/laravel-authentication
Deskripsi: Layout kartu terpusat (Single Card Layout) dengan background ambient glow.
           Mendukung Dark & Light Mode penuh, bersih, minimalis, dan elegan.
=============================================================================
--}}
@props([
    'title' => null,
])

<x-authentication::layouts.auth :title="$title">
    <div class="min-h-screen w-full flex flex-col justify-center items-center p-4 sm:p-6 lg:p-8 bg-slate-50 dark:bg-slate-950 relative overflow-x-hidden">
        
        {{-- Background Grid & Subtle Ambient Effects --}}
        <div class="absolute inset-0 bg-[linear-gradient(to_right,#00000005_1px,transparent_1px),linear-gradient(to_bottom,#00000005_1px,transparent_1px)] dark:bg-[linear-gradient(to_right,#ffffff08_1px,transparent_1px),linear-gradient(to_bottom,#ffffff08_1px,transparent_1px)] bg-[size:28px_28px] pointer-events-none"></div>
        <div class="absolute top-1/4 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[400px] h-[300px] bg-amber-500/10 dark:bg-amber-500/10 rounded-full blur-[100px] pointer-events-none"></div>

        <div class="w-full max-w-md relative z-10 space-y-6">
            
            {{-- Header Brand Terpusat --}}
            <div class="text-center space-y-2">
                <div class="inline-flex items-center justify-center w-11 h-11 rounded-xl bg-gradient-to-tr from-amber-500 to-amber-400 shadow-md shadow-amber-500/10 text-slate-950 font-bold">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                    </svg>
                </div>
                <div>
                    <h1 class="font-heading font-bold text-xl sm:text-2xl tracking-tight text-slate-900 dark:text-white">
                        {{ config('authentication.ui.brand_name', config('app.name', 'Console Auth')) }}
                    </h1>
                    <p class="text-[11px] text-slate-500 dark:text-slate-400 font-mono-code mt-0.5">
                        {{ config('authentication.ui.brand_badge', 'SECURE IDENTITY GATEWAY') }}
                    </p>
                </div>
            </div>

            {{-- Kartu Utama (Clean Card with Responsive Light & Dark Border) --}}
            <div class="bg-white dark:bg-slate-900/80 backdrop-blur-xl border border-slate-200 dark:border-slate-800 rounded-2xl p-6 sm:p-8 shadow-xl shadow-slate-200/50 dark:shadow-2xl dark:shadow-black/50">
                {{ $slot }}
            </div>

            {{-- Footer Keamanan Terpusat --}}
            <footer class="text-center text-xs text-slate-500 font-mono-code space-y-0.5">
                <p>&copy; {{ date('Y') }} {{ config('app.name', 'Laravel') }}. All rights reserved.</p>
                <p class="text-[11px] text-slate-400 dark:text-slate-600">Fail-Closed &bull; End-to-End Protected</p>
            </footer>

        </div>
    </div>
</x-authentication::layouts.auth>
