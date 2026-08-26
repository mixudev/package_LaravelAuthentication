{{-- 
=============================================================================
KOMPONEN: FORM HEADER
Package: mixudev/laravel-authentication
Deskripsi: Komponen header untuk judul dan deskripsi singkat pada formulir autentikasi.
=============================================================================
--}}
@props([
    'title',
    'subtitle' => null,
    'badge' => null,
])

<div class="space-y-2 mb-6">
    @if ($badge)
        <div class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-md text-[11px] font-mono-code bg-amber-500/10 border border-amber-500/20 text-amber-300 mb-1">
            {{ $badge }}
        </div>
    @endif

    <h2 class="font-heading font-bold text-2xl sm:text-3xl text-white tracking-tight">
        {{ $title }}
    </h2>

    @if ($subtitle)
        <p class="text-sm text-slate-400 leading-relaxed">
            {{ $subtitle }}
        </p>
    @endif
</div>
