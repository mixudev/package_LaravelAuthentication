{{-- 
=============================================================================
KOMPONEN: FORM HEADER (MINIMALIS BREEZE)
Package: mixudev/laravel-authentication
Deskripsi: Judul dan deskripsi singkat formulir bergaya bersih ala Laravel Breeze.
=============================================================================
--}}
@props([
    'title',
    'subtitle' => null,
    'badge' => null,
])

<div class="mb-5 text-left">
    <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">
        {{ $title }}
    </h2>

    @if ($subtitle)
        <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
            {{ $subtitle }}
        </p>
    @endif
</div>
