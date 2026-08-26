{{-- 
=============================================================================
KOMPONEN: PEMISAH / DIVIDER
Package: mixudev/laravel-authentication
Deskripsi: Garis pemisah horizontal dengan label di tengah (misal: 'ATAU MASUK DENGAN').
=============================================================================
--}}
@props([
    'label' => 'ATAU',
])

<div class="relative my-5">
    <div class="absolute inset-0 flex items-center" aria-hidden="true">
        <div class="w-full border-t border-slate-200 dark:border-slate-800"></div>
    </div>
    <div class="relative flex justify-center">
        <span class="bg-white dark:bg-slate-950 px-3 text-[10px] font-mono-code font-semibold tracking-wider text-slate-500 dark:text-slate-400 uppercase">
            {{ $label }}
        </span>
    </div>
</div>
