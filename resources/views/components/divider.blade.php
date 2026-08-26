{{-- 
=============================================================================
KOMPONEN: PEMISAH / DIVIDER (CLEAN BREEZE STYLE)
Package: mixudev/laravel-authentication
Deskripsi: Garis pemisah horizontal bersih dengan teks di tengah.
=============================================================================
--}}
@props([
    'label' => 'ATAU',
])

<div class="relative my-4">
    <div class="absolute inset-0 flex items-center" aria-hidden="true">
        <div class="w-full border-t border-gray-200 dark:border-gray-700"></div>
    </div>
    <div class="relative flex justify-center text-xs uppercase">
        <span class="bg-white dark:bg-gray-800 px-2 text-gray-500 dark:text-gray-400">
            {{ $label }}
        </span>
    </div>
</div>
