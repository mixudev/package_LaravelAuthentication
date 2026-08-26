{{-- 
=============================================================================
KOMPONEN: ALERT / NOTIFIKASI STATUS
Package: mixudev/laravel-authentication
Deskripsi: Menampilkan notifikasi pesan sukses, error, peringatan, atau info
           yang dikirim melalui session flash Laravel (e.g. session('status')).
=============================================================================
--}}
@props([
    'type' => 'info', // 'success', 'error', 'warning', 'info'
    'message' => null,
])

@php
    $typeStyles = [
        'success' => [
            'wrapper' => 'bg-emerald-950/40 border-emerald-500/40 text-emerald-300',
            'icon'    => 'text-emerald-400',
            'svg'     => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>',
        ],
        'error' => [
            'wrapper' => 'bg-rose-950/40 border-rose-500/40 text-rose-300',
            'icon'    => 'text-rose-400',
            'svg'     => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>',
        ],
        'warning' => [
            'wrapper' => 'bg-amber-950/40 border-amber-500/40 text-amber-300',
            'icon'    => 'text-amber-400',
            'svg'     => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>',
        ],
        'info' => [
            'wrapper' => 'bg-blue-950/40 border-blue-500/40 text-blue-300',
            'icon'    => 'text-blue-400',
            'svg'     => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>',
        ],
    ];

    $currentStyle = $typeStyles[$type] ?? $typeStyles['info'];
@endphp

<div {{ $attributes->merge(['class' => 'flex items-start gap-3 p-3.5 rounded-xl border text-xs leading-relaxed ' . $currentStyle['wrapper']]) }} role="alert">
    <div class="flex-shrink-0 mt-0.5 {{ $currentStyle['icon'] }}">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            {!! $currentStyle['svg'] !!}
        </svg>
    </div>
    
    <div class="flex-1 font-medium">
        {{ $message ?? $slot }}
    </div>
</div>
