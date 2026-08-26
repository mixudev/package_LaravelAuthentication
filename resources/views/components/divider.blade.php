{{-- 
=============================================================================
KOMPONEN: PEMISAH / DIVIDER (MODERN ZINC)
Package: mixudev/laravel-authentication
Deskripsi: Garis pemisah horizontal bersih dengan teks di tengah.
=============================================================================
--}}
@props([
    'label' => 'ATAU',
])

<div class="relative my-4">
    <div class="absolute inset-0 flex items-center" aria-hidden="true">
        <div class="auth-divider w-full border-t"></div>
    </div>
    <div class="relative flex justify-center text-xs uppercase tracking-wider">
        <span class="auth-divider-text px-2.5 text-[11px] font-medium">
            {{ $label }}
        </span>
    </div>
</div>
