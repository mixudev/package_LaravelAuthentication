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
    'loading' => false, // Manual loading state
    'loadingText' => null, // Text saat loading (default: translatable)
])

@php
    $loadingText = $loadingText ?? __('authentication::messages.processing');
    $baseClasses = 'inline-flex items-center justify-center rounded-lg font-medium transition duration-150 focus:outline-none focus:ring-2 focus:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed cursor-pointer select-none';

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
    x-data="{}"
    x-bind:disabled="$el.closest('form')?.submitting || false"
>
    @if (isset($icon))
        <span class="mr-2 -ml-1 flex items-center" x-show="!($el.closest('form')?.submitting || false)">{{ $icon }}</span>
    @endif

    {{-- Loading Spinner --}}
    <svg 
        x-show="$el.closest('form')?.submitting || false" 
        x-cloak
        class="animate-spin -ml-1 mr-2 h-4 w-4" 
        fill="none" 
        viewBox="0 0 24 24"
    >
        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
    </svg>

    <span x-show="!($el.closest('form')?.submitting || false)">{{ $slot }}</span>
    <span x-show="$el.closest('form')?.submitting || false" x-cloak>{{ $loadingText }}</span>

    @if (isset($suffix))
        <span class="ml-2 -mr-1 flex items-center" x-show="!($el.closest('form')?.submitting || false)">{{ $suffix }}</span>
    @endif
</button>
