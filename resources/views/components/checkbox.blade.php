{{-- 
=============================================================================
KOMPONEN: CUSTOM CHECKBOX (CLEAN BREEZE STYLE)
Package: mixudev/laravel-authentication
Deskripsi: Checkbox standar Laravel Breeze untuk 'Ingat Saya' atau 'Syarat Ketentuan'.
=============================================================================
--}}
@props([
    'name',
    'id' => null,
    'label' => null,
    'checked' => false,
    'required' => false,
])

@php
    $checkboxId = $id ?? $name;
    $isChecked = old($name, $checked);
@endphp

<div class="flex items-center">
    <input 
        {{ $attributes->merge([
            'id' => $checkboxId,
            'name' => $name,
            'type' => 'checkbox',
            'required' => $required,
            'checked' => (bool) $isChecked,
        ]) }}
        class="rounded dark:bg-gray-900 border-gray-300 dark:border-gray-700 text-indigo-600 shadow-sm focus:ring-indigo-500 dark:focus:ring-indigo-600 dark:focus:ring-offset-gray-800 cursor-pointer"
    />
    
    @if ($label || !$slot->isEmpty())
        <label for="{{ $checkboxId }}" class="ms-2 text-sm text-gray-600 dark:text-gray-400 select-none cursor-pointer">
            {{ $label ?? $slot }}
        </label>
    @endif
</div>
