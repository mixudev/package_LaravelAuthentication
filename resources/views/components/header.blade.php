{{-- 
=============================================================================
KOMPONEN: FORM HEADER (MODERN ZINC)
Package: mixudev/laravel-authentication
Deskripsi: Judul dan deskripsi singkat formulir bergaya bersih & modern.
=============================================================================
--}}
@props([
    'title',
    'subtitle' => null,
    'badge' => null,
])

<div class="mb-5 text-left">
    <h2 class="text-lg font-semibold text-zinc-900 dark:text-zinc-100 tracking-tight">
        {{ $title }}
    </h2>

    @if ($subtitle)
        <p class="text-sm text-zinc-600 dark:text-zinc-400 mt-1">
            {{ $subtitle }}
        </p>
    @endif
</div>
