{{-- 
=============================================================================
KOMPONEN: FORM INPUT BERSTANDAR KEAMANAN TINGGI
Package: mixudev/laravel-authentication
Deskripsi: Komponen input universal dengan dukungan validasi Laravel otomatis (@error),
           toggle visibilitas password (show/hide), integrasi icon, dan styling Tailwind CSS.
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
            <label for="{{ $inputId }}" class="block text-xs font-medium font-mono-code uppercase tracking-wider text-slate-300">
                {{ $label }}
                @if ($required)
                    <span class="text-amber-400 font-bold" title="Wajib diisi">*</span>
                @endif
            </label>

            {{-- Slot untuk link samping label (misal: 'Lupa Password?') --}}
            @if (isset($labelRight))
                <div>{{ $labelRight }}</div>
            @endif
        </div>
    @endif

    {{-- Container Input & Icon / Action --}}
    <div class="relative rounded-xl shadow-sm">
        
        {{-- Slot Icon Kiri (Optional) --}}
        @if (isset($icon))
            <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                {{ $icon }}
            </div>
        @endif

        {{-- Elemen Input Utama --}}
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
            class="block w-full rounded-xl bg-slate-900/90 border text-slate-100 placeholder-slate-500 text-sm transition-all duration-200
                {{ isset($icon) ? 'pl-10' : 'pl-4' }}
                {{ $isPassword ? 'pr-11' : 'pr-4' }}
                py-2.5 sm:py-3
                {{ $hasError 
                    ? 'border-rose-500/80 focus:border-rose-500 focus:ring-2 focus:ring-rose-500/20 text-rose-100 bg-rose-950/10' 
                    : 'border-slate-800 focus:border-amber-500 focus:ring-2 focus:ring-amber-500/20 hover:border-slate-700' 
                }}
                outline-none"
        />

        {{-- Tombol Toggle Password (Hanya muncul jika type="password") --}}
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
                class="absolute inset-y-0 right-0 pr-3.5 flex items-center text-slate-400 hover:text-slate-200 transition-colors focus:outline-none"
                tabindex="-1"
                aria-label="Tampilkan atau sembunyikan kata sandi"
            >
                {{-- Icon Mata Terbuka --}}
                <svg class="eye-open w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                </svg>
                {{-- Icon Mata Tertutup --}}
                <svg class="eye-closed w-5 h-5 hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l18 18"/>
                </svg>
            </button>
        @endif

    </div>

    {{-- Pesan Hint Tambahan --}}
    @if ($hint && !$hasError)
        <p class="text-[11px] text-slate-400">{{ $hint }}</p>
    @endif

    {{-- Pesan Validasi Error Otomatis (@error) --}}
    @if (isset($errors) && $errors->has($name))
        <p class="text-xs text-rose-400 flex items-center gap-1 mt-1 font-medium">
            <svg class="w-3.5 h-3.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            <span>{{ $errors->first($name) }}</span>
        </p>
    @endif
</div>
