{{-- 
=============================================================================
KOMPONEN: TOMBOL INTERAKTIF (CLEAN BREEZE STYLE)
Package: mixudev/laravel-authentication
Deskripsi: Tombol standar Laravel Breeze (Primary, Secondary, Outline, Danger).
=============================================================================
--}}
@props([
    'type' => 'submit',
    'variant' => 'primary',
    'size' => 'md',
    'fullWidth' => true,
])

@php
    $baseClasses = 'inline-flex items-center justify-center rounded-md font-semibold tracking-wide transition ease-in-out duration-150 focus:outline-none focus:ring-2 focus:ring-offset-2 disabled:opacity-50 cursor-pointer';

    $variants = [
        // Varian Primary: Standar Laravel Breeze Dark / Light
        'primary' => 'bg-gray-800 dark:bg-gray-200 text-white dark:text-gray-800 hover:bg-gray-700 dark:hover:bg-white focus:bg-gray-700 dark:focus:bg-white active:bg-gray-900 dark:active:bg-gray-300 focus:ring-indigo-500 dark:focus:ring-offset-gray-800 shadow-sm',
        
        // Varian Secondary: Abu-abu netral
        'secondary' => 'bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-500 text-gray-700 dark:text-gray-300 shadow-sm hover:bg-gray-50 dark:hover:bg-gray-700 focus:ring-indigo-500 dark:focus:ring-offset-gray-800',
        
        // Varian Outline
        'outline' => 'bg-transparent border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800 focus:ring-indigo-500',
        
        // Varian Danger
        'danger' => 'bg-red-600 text-white hover:bg-red-500 active:bg-red-700 focus:ring-red-500 shadow-sm',
    ];

    $sizes = [
        'sm' => 'px-3 py-1.5 text-xs',
        'md' => 'px-4 py-2 text-sm',
        'lg' => 'px-5 py-2.5 text-base',
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
