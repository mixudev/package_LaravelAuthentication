{{-- 
=============================================================================
KOMPONEN: ALERT / NOTIFIKASI STATUS (MODERN ZINC)
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
        'success' => 'text-emerald-700 dark:text-emerald-400 bg-emerald-50 dark:bg-emerald-950/40 border-emerald-200 dark:border-emerald-900/60',
        'error'   => 'text-red-700 dark:text-red-400 bg-red-50 dark:bg-red-950/40 border-red-200 dark:border-red-900/60',
        'warning' => 'text-amber-700 dark:text-amber-400 bg-amber-50 dark:bg-amber-950/40 border-amber-200 dark:border-amber-900/60',
        'info'    => 'text-blue-700 dark:text-blue-400 bg-blue-50 dark:bg-blue-950/40 border-blue-200 dark:border-blue-900/60',
    ];

    $currentStyle = $typeStyles[$type] ?? $typeStyles['info'];
@endphp

<div {{ $attributes->merge(['class' => 'p-3 rounded-lg border text-sm font-medium ' . $currentStyle]) }} role="alert">
    {{ $message ?? $slot }}
</div>
