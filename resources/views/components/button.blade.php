{{-- 
=============================================================================
KOMPONEN: TOMBOL INTERAKTIF (MODERN DEEP ZINC)
Package: mixudev/laravel-authentication
Deskripsi: Tombol standar modern (Primary, Secondary, Outline, Danger).
=============================================================================
--}}
@props([
    'type' => 'submit',
    'variant' => 'primary',
    'size' => 'md',
    'fullWidth' => true,
])

@php
    $baseClasses = 'inline-flex items-center justify-center rounded-lg font-medium transition duration-150 focus:outline-none focus:ring-2 focus:ring-offset-2 disabled:opacity-50 cursor-pointer select-none';

    $variants = [
        // Varian Primary: Modern Deep Zinc / High Contrast White
        'primary' => 'auth-btn-primary bg-zinc-900 dark:bg-zinc-100 text-white dark:text-zinc-950 hover:bg-zinc-800 dark:hover:bg-white focus:ring-zinc-900 dark:focus:ring-zinc-400 dark:focus:ring-offset-zinc-950 shadow-xs',
        
        // Varian Secondary: Netral
        'secondary' => 'auth-btn-secondary bg-white dark:bg-zinc-900 border border-zinc-300 dark:border-zinc-800 text-zinc-800 dark:text-zinc-200 shadow-xs hover:bg-zinc-50 dark:hover:bg-zinc-800/80 focus:ring-zinc-400 dark:focus:ring-offset-zinc-950',
        
        // Varian Outline
        'outline' => 'bg-transparent border border-zinc-300 dark:border-zinc-800 text-zinc-700 dark:text-zinc-300 hover:bg-zinc-100 dark:hover:bg-zinc-900 focus:ring-zinc-500',
        
        // Varian Danger
        'danger' => 'bg-red-600 text-white hover:bg-red-500 active:bg-red-700 focus:ring-red-500 shadow-xs',
    ];

    $sizes = [
        'sm' => 'px-3 py-1.5 text-xs',
        'md' => 'px-4 py-2.5 text-sm',
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
    @if (isset($icon))
        <span class="mr-2 -ml-1 flex items-center">{{ $icon }}</span>
    @endif

    <span>{{ $slot }}</span>

    @if (isset($suffix))
        <span class="ml-2 -mr-1 flex items-center">{{ $suffix }}</span>
    @endif
</button>
