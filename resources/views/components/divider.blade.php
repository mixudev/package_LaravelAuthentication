{{-- 
=============================================================================
KOMPONEN: PEMISAH / DIVIDER
Package: mixudev/laravel-authentication
Deskripsi: Garis pemisah horizontal dengan teks di tengah.
           Warna mengikuti konteks tema (light/dark) via class auth-divider-text.
=============================================================================
--}}
@props([
    'label' => null,
])

<div class="relative my-5" role="separator" aria-hidden="true">
    <div class="absolute inset-0 flex items-center">
        <div class="auth-divider w-full border-t border-zinc-200 dark:border-zinc-800"></div>
    </div>
    @if ($label)
        <div class="relative flex justify-center">
            <span class="auth-divider-text px-3 text-[11px] font-medium uppercase tracking-widest text-zinc-400 dark:text-zinc-500 bg-white dark:bg-zinc-900">
                {{ $label }}
            </span>
        </div>
    @endif
</div>
