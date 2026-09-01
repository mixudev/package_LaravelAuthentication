{{-- 
=============================================================================
KOMPONEN: ALERT / NOTIFIKASI STATUS
Package: mixudev/laravel-authentication
Deskripsi: Notifikasi pesan sukses, error, peringatan, atau info dengan ikon.
           Mendukung auto-dismiss otomatis dengan animasi fade-out.
           Warna dikendalikan oleh .auth-alert-{type} dari scoped CSS layout.
=============================================================================
--}}
@props([
    'type'        => 'info',
    'message'     => null,
    'autodismiss' => false,
    'duration'    => 4000,
])

@php
    $icons = [
        'success' => '<path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>',
        'error'   => '<path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>',
        'warning' => '<path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>',
        'info'    => '<path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>',
    ];
    $icon = $icons[$type] ?? $icons['info'];

    $displayText = $message;
    if (is_array($displayText)) {
        $displayText = implode(' ', \Illuminate\Support\Arr::flatten($displayText));
    }
@endphp

<div
    {{ $attributes->merge(['class' => 'auth-alert-' . $type . ' flex items-start gap-2.5 p-3.5 rounded-lg border text-xs leading-relaxed font-medium']) }}
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
    <svg class="w-4 h-4 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
        {!! $icon !!}
    </svg>
    <span>{{ $displayText ?: $slot }}</span>
</div>
