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
    <h2 class="auth-heading text-lg font-semibold tracking-tight">
        {{ $title }}
    </h2>

    @if ($subtitle)
        <p class="auth-subtext text-sm mt-1">
            {{ $subtitle }}
        </p>
    @endif
</div>
