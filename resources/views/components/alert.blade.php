{{-- 
=============================================================================
KOMPONEN: ALERT / NOTIFIKASI STATUS (DENGAN AUTO-DISMISS)
Package: mixudev/laravel-authentication
Deskripsi: Notifikasi pesan sukses, error, peringatan, atau info.
           Mendukung auto-dismiss otomatis dengan animasi fade-out.
=============================================================================
--}}
@props([
    'type'        => 'info',    
    'message'     => null,
    'autodismiss' => false,     
    'duration'    => 3000,    
])

<div
    {{ $attributes->merge(['class' => 'auth-alert-' . $type . ' p-3.5 rounded-lg border text-xs leading-relaxed font-medium']) }}
    role="alert"
    @if ($autodismiss)
    x-data="{ visible: true }"
    x-show="visible"
    x-init="setTimeout(() => { visible = false }, {{ $duration }})"
    x-transition:leave="transition ease-in duration-500"
    x-transition:leave-start="opacity-100 translate-y-0"
    x-transition:leave-end="opacity-0 -translate-y-1"
    style="transform: translateY(0);"
    @endif
>
    {{ $message ?? $slot }}
</div>
