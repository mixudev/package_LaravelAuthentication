{{-- 
=============================================================================
KOMPONEN: ALERT / NOTIFIKASI STATUS (CLEAN BREEZE STYLE)
Package: mixudev/laravel-authentication
Deskripsi: Menampilkan notifikasi pesan sukses, error, atau info secara bersih.
=============================================================================
--}}
@props([
    'type' => 'info', // 'success', 'error', 'warning', 'info'
    'message' => null,
])

@php
    $typeStyles = [
        'success' => 'text-green-600 dark:text-green-400 bg-green-50 dark:bg-green-900/30 border-green-200 dark:border-green-800',
        'error'   => 'text-red-600 dark:text-red-400 bg-red-50 dark:bg-red-900/30 border-red-200 dark:border-red-800',
        'warning' => 'text-amber-600 dark:text-amber-400 bg-amber-50 dark:bg-amber-900/30 border-amber-200 dark:border-amber-800',
        'info'    => 'text-blue-600 dark:text-blue-400 bg-blue-50 dark:bg-blue-900/30 border-blue-200 dark:border-blue-800',
    ];

    $currentStyle = $typeStyles[$type] ?? $typeStyles['info'];
@endphp

<div {{ $attributes->merge(['class' => 'p-3 rounded-md border text-sm font-medium ' . $currentStyle]) }} role="alert">
    {{ $message ?? $slot }}
</div>
