{{-- 
=============================================================================
KOMPONEN: CUSTOM CHECKBOX
Package: mixudev/laravel-authentication
Deskripsi: Komponen checkbox modern dengan styling Tailwind, state checked,
           serta label interaktif untuk fitur 'Ingat Saya' atau 'Syarat & Ketentuan'.
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

<div class="flex items-center space-x-2.5">
    <input 
        {{ $attributes->merge([
            'id' => $checkboxId,
            'name' => $name,
            'type' => 'checkbox',
            'required' => $required,
            'checked' => (bool) $isChecked,
        ]) }}
        class="w-4 h-4 rounded bg-white dark:bg-slate-900 border-slate-300 dark:border-slate-700 text-amber-500 focus:ring-amber-500/30 focus:ring-offset-0 focus:ring-2 cursor-pointer transition-all"
    />
    
    @if ($label || !$slot->isEmpty())
        <label for="{{ $checkboxId }}" class="text-xs text-slate-600 dark:text-slate-400 select-none cursor-pointer hover:text-slate-900 dark:hover:text-slate-300">
            {{ $label ?? $slot }}
        </label>
    @endif
</div>
