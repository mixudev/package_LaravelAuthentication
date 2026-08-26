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
        <div class="w-full border-t border-zinc-200 dark:border-zinc-800"></div>
    </div>
    <div class="relative flex justify-center text-xs uppercase tracking-wider">
        <span class="bg-white dark:bg-zinc-900 px-2.5 text-[11px] font-medium text-zinc-400 dark:text-zinc-500">
            {{ $label }}
        </span>
    </div>
</div>
