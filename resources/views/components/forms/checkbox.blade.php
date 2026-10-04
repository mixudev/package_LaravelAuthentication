{{-- 
=============================================================================
KOMPONEN: CUSTOM CHECKBOX (MODERN ZINC)
Package: mixudev/laravel-authentication
Deskripsi: Checkbox bersih dan modern untuk 'Ingat Saya' atau 'Syarat Ketentuan'.
=============================================================================
--}}
@props([
    'name',
    'id' => null,
    'value' => '1',
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
            'value' => $value,
            'type' => 'checkbox',
            'required' => $required,
            'checked' => (bool) $isChecked,
        ]) }}
        class="w-4 h-4 rounded border-zinc-300 dark:border-zinc-800 text-zinc-900 dark:text-zinc-100 shadow-xs focus:ring-1 focus:ring-zinc-900 dark:focus:ring-zinc-400 cursor-pointer"
    />
    
    @if ($label || !$slot->isEmpty())
        <label for="{{ $checkboxId }}" class="auth-checkbox-label ms-2 text-sm select-none cursor-pointer">
            {{ $label ?? $slot }}
        </label>
    @endif
</div>
