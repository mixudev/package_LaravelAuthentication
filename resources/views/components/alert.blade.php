{{-- 
=============================================================================
KOMPONEN: ALERT / NOTIFIKASI STATUS (CLEAN BREEZE STYLE)
Package: mixudev/laravel-authentication
Deskripsi: Menampilkan notifikasi pesan sukses, error, peringatan, atau info.
=============================================================================
--}}
@props([
    'type' => 'info', // 'success', 'error', 'warning', 'info'
    'message' => null,
])

<div {{ $attributes->merge(['class' => 'auth-alert-' . $type . ' p-3.5 rounded-lg border text-xs leading-relaxed font-medium']) }} role="alert">
    {{ $message ?? $slot }}
</div>
