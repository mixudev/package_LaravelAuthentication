{{-- 
=============================================================================
KOMPONEN: FORM INPUT (MODERN ZINC)
Package: mixudev/laravel-authentication
Deskripsi: Input form standar dengan label, error binding, dan toggle password.
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

<div class="space-y-1.5" x-data="{ showPass: false }">
    {{-- Label Input --}}
    @if ($label)
        <div class="flex items-center justify-between">
            <label for="{{ $inputId }}" class="auth-label block font-medium text-xs uppercase tracking-wider">
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
            class="auth-input block w-full border rounded-lg shadow-xs text-sm px-3.5 py-2.5 outline-none transition duration-150
                {{ $isPassword ? 'pr-10' : '' }}
                {{ $hasError ? 'border-red-500! focus:border-red-500! focus:ring-red-500!' : '' }}"
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
                class="absolute inset-y-0 right-0 pr-3.5 flex items-center text-zinc-400 hover:text-zinc-600 dark:hover:text-zinc-200 transition-colors focus:outline-none cursor-pointer"
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
        <p class="auth-subtext text-xs mt-1">{{ $hint }}</p>
    @endif

    {{-- Error --}}
    @if (isset($errors) && $errors->has($name))
        <p class="text-xs text-red-600 dark:text-red-400 mt-1 font-medium flex items-center gap-1">
            <svg class="w-3.5 h-3.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            <span>{{ $errors->first($name) }}</span>
        </p>
    @endif
</div>
