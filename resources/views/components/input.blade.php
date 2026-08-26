{{-- 
=============================================================================
KOMPONEN: FORM INPUT (CLEAN BREEZE STYLE)
Package: mixudev/laravel-authentication
Deskripsi: Input form standar Laravel Breeze dengan label, error binding, dan toggle password.
=============================================================================
--}}
@props([
    'name',
    'id' => null,
    'type' => 'text',
    'label' => null,
    'placeholder' => '',
    'value' => null,
    'required' => false,
    'autocomplete' => null,
    'autofocus' => false,
    'hint' => null,
    'showTogglePassword' => true,
])

@php
    $inputId = $id ?? $name;
    $hasError = isset($errors) ? $errors->has($name) : false;
    $inputValue = old($name, $value);
    $isPassword = ($type === 'password');
@endphp

<div class="space-y-1" x-data="{ showPass: false }">
    {{-- Label Input --}}
    @if ($label)
        <div class="flex items-center justify-between">
            <label for="{{ $inputId }}" class="block font-medium text-sm text-gray-700 dark:text-gray-300">
                {{ $label }}
                @if ($required)
                    <span class="text-red-500">*</span>
                @endif
            </label>

            {{-- Link samping label (misal: Lupa Password) --}}
            @if (isset($labelRight))
                <div>{{ $labelRight }}</div>
            @endif
        </div>
    @endif

    {{-- Container Input --}}
    <div class="relative">
        <input 
            {{ $attributes->merge([
                'id' => $inputId,
                'name' => $name,
                'type' => $type,
                'value' => $inputValue,
                'placeholder' => $placeholder,
                'required' => $required,
                'autocomplete' => $autocomplete,
                'autofocus' => $autofocus,
            ]) }}
            @if ($isPassword)
                :type="showPass ? 'text' : 'password'"
            @endif
            class="block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm text-sm px-3 py-2
                {{ $isPassword ? 'pr-10' : '' }}
                {{ $hasError ? 'border-red-500 dark:border-red-500 focus:border-red-500 focus:ring-red-500' : '' }}"
        />

        {{-- Toggle Password Button --}}
        @if ($isPassword && $showTogglePassword)
            <button 
                type="button" 
                @click="showPass = !showPass"
                onclick="
                    const inp = document.getElementById('{{ $inputId }}');
                    if (inp) {
                        const isText = inp.type === 'text';
                        inp.type = isText ? 'password' : 'text';
                        this.querySelector('.eye-open').classList.toggle('hidden', isText);
                        this.querySelector('.eye-closed').classList.toggle('hidden', !isText);
                    }
                "
                class="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 transition-colors focus:outline-none"
                tabindex="-1"
                aria-label="Tampilkan atau sembunyikan kata sandi"
            >
                <svg class="eye-open w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                </svg>
                <svg class="eye-closed w-4 h-4 hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l18 18"/>
                </svg>
            </button>
        @endif
    </div>

    {{-- Hint --}}
    @if ($hint && !$hasError)
        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">{{ $hint }}</p>
    @endif

    {{-- Error --}}
    @if (isset($errors) && $errors->has($name))
        <p class="text-sm text-red-600 dark:text-red-400 mt-1">
            {{ $errors->first($name) }}
        </p>
    @endif
</div>
