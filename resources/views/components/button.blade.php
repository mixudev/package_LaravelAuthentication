{{-- 
=============================================================================
KOMPONEN: TOMBOL INTERAKTIF (BUTTON)
Package: mixudev/laravel-authentication
Deskripsi: Komponen tombol serbaguna dengan berbagai varian tema (primary, secondary,
           outline, danger), dukungan transisi Tailwind, focus-ring, dan Light/Dark mode.
=============================================================================
--}}
@props([
    'type' => 'submit',
    'variant' => 'primary',
    'size' => 'md',
    'fullWidth' => true,
])

@php
    $baseClasses = 'inline-flex items-center justify-center font-medium transition-all duration-150 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-offset-white dark:focus:ring-offset-slate-950 disabled:opacity-50 disabled:cursor-not-allowed cursor-pointer select-none rounded-xl';

    $variants = [
        // Varian Primary: Amber/Gold Accent khas Console Auth
        'primary' => 'bg-amber-500 hover:bg-amber-400 active:bg-amber-600 text-slate-950 font-semibold shadow-sm focus:ring-amber-500 border border-amber-400/30',
        
        // Varian Secondary: Kontras halus Light & Dark
        'secondary' => 'bg-slate-100 hover:bg-slate-200 text-slate-800 dark:bg-slate-800 dark:hover:bg-slate-700 dark:text-slate-200 border border-slate-200 dark:border-slate-750 focus:ring-slate-400',
        
        // Varian Outline: Border transparan dengan efek hover
        'outline' => 'bg-transparent text-slate-700 dark:text-slate-300 border border-slate-300 dark:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-900 focus:ring-slate-500',
        
        // Varian Danger: Untuk aksi destruktif atau pembatalan kritis
        'danger' => 'bg-rose-600 text-white shadow-sm hover:bg-rose-500 active:bg-rose-700 focus:ring-rose-500 border border-rose-500/30',
    ];

    $sizes = [
        'sm' => 'px-3 py-1.5 text-xs',
        'md' => 'px-4 py-2.5 sm:py-2.5 text-sm',
        'lg' => 'px-5 py-3 text-base',
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
        <span class="mr-2 -ml-1 flex items-center">{{ $icon }}</span>
    @endif

    {{-- Konten Teks Tombol --}}
    <span>{{ $slot }}</span>

    {{-- Slot Suffix (misal: panah arrow-right) --}}
    @if (isset($suffix))
        <span class="ml-2 -mr-1 flex items-center">{{ $suffix }}</span>
    @endif
</button>
