{{-- 
=============================================================================
KOMPONEN: TOMBOL INTERAKTIF (BUTTON)
Package: mixudev/laravel-authentication
Deskripsi: Komponen tombol serbaguna dengan berbagai varian tema (primary, secondary,
           outline, danger), dukungan transisi Tailwind, focus-ring, dan accessibility.
=============================================================================
--}}
@props([
    'type' => 'submit',
    'variant' => 'primary',
    'size' => 'md',
    'fullWidth' => true,
])

@php
    $baseClasses = 'inline-flex items-center justify-center font-medium transition-all duration-150 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-offset-slate-950 disabled:opacity-50 disabled:cursor-not-allowed cursor-pointer select-none rounded-xl';

    $variants = [
        // Varian Primary: Amber/Gold Accent khas Console Auth
        'primary' => 'bg-gradient-to-r from-amber-500 to-amber-400 text-slate-950 font-semibold shadow-lg shadow-amber-500/20 hover:from-amber-400 hover:to-amber-300 focus:ring-amber-500 border border-amber-400/30',
        
        // Varian Secondary: Slate gelap kontras
        'secondary' => 'bg-slate-800 text-slate-200 hover:bg-slate-700 hover:text-white focus:ring-slate-500 border border-slate-750',
        
        // Varian Outline: Border transparan dengan efek hover
        'outline' => 'bg-transparent text-slate-300 border border-slate-700 hover:border-slate-500 hover:bg-slate-900 focus:ring-slate-600',
        
        // Varian Danger: Untuk aksi destruktif atau pembatalan kritis
        'danger' => 'bg-rose-600 text-white shadow-lg shadow-rose-600/20 hover:bg-rose-500 focus:ring-rose-500 border border-rose-500/30',
    ];

    $sizes = [
        'sm' => 'px-3 py-2 text-xs',
        'md' => 'px-4 py-2.5 sm:py-3 text-sm',
        'lg' => 'px-5 py-3.5 text-base',
    ];

    $classes = $baseClasses . ' ' 
        . ($variants[$variant] ?? $variants['primary']) . ' ' 
        . ($sizes[$size] ?? $sizes['md']) . ' ' 
        . ($fullWidth ? 'w-full' : '');
@endphp

<button 
    {{ $attributes->merge([
        'type' => $type,
        'class' => $classes,
    ]) }}
>
    {{-- Slot Icon / Prefix --}}
    @if (isset($icon))
        <span class="mr-2 -ml-1">{{ $icon }}</span>
    @endif

    {{-- Konten Teks Tombol --}}
    <span>{{ $slot }}</span>

    {{-- Slot Suffix (misal: panah arrow-right) --}}
    @if (isset($suffix))
        <span class="ml-2 -mr-1">{{ $suffix }}</span>
    @endif
</button>
