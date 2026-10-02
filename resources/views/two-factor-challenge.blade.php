{{-- 
=============================================================================
HALAMAN VIEW: TWO-FACTOR CHALLENGE
Package: mixudev/laravel-authentication
Deskripsi: Halaman verifikasi TOTP / Backup Code saat login dengan 2FA aktif.
=============================================================================
--}}
@php
    $activeLayout = config('authentication.ui.layout', 'card') === 'split' 
        ? 'authentication::layouts.split' 
        : 'authentication::layouts.card';

    $verifyRoute = Route::has('two-factor.verify') 
        ? route('two-factor.verify') 
        : url('/two-factor-challenge');

    $inputMode = $inputMode ?? 'totp';
    $totpDigits = (int) config('authentication.features.two_factor.digits', 6);
@endphp

<x-dynamic-component :component="$activeLayout" :title="__('authentication::messages.two_factor_title')">
    
    <div class="space-y-4" x-data="{ recovery: @js($inputMode === 'recovery') }">
        
        {{-- Header Halaman --}}
        <x-authentication::header 
            :title="__('authentication::messages.two_factor_title')"
            :subtitle="__('authentication::messages.two_factor_subtitle')"
        />

        {{-- Alert Notifikasi --}}
        @if ($errors->any())
            <x-authentication::alert type="error" :autodismiss="true" :message="$errors->first()" />
        @endif

        {{-- Form Verifikasi 2FA --}}
        <form method="POST" action="{{ $verifyRoute }}" class="space-y-4"
              x-data="{ submitting: false }"
              @submit="submitting = true">
            @csrf

            {{-- Input Kode TOTP 6-Digit --}}
            <div x-show="!recovery">
                <x-authentication::segmented-code-input
                    name="code"
                    :length="$totpDigits"
                    charset="numeric"
                    autocomplete="one-time-code"
                    :autofocus="$inputMode === 'totp'"
                    activeWhen="!recovery"
                    label="Kode Keamanan {{ $totpDigits }}-Digit"
                />
            </div>

            {{-- Input Kode Pemulihan Cadangan (10 karakter: ABCDE-12345) --}}
            <div x-show="recovery" x-cloak>
                <x-authentication::segmented-code-input
                    name="recovery_code"
                    :length="10"
                    charset="alphanumeric-uppercase"
                    autocomplete="off"
                    :autofocus="$inputMode === 'recovery'"
                    activeWhen="recovery"
                    label="Kode Pemulihan Cadangan"
                    sizeClass="w-7 h-10 text-sm sm:w-8 sm:h-11"
                    gapClass="gap-1"
                />
            </div>

            {{-- Checkbox Percayai Perangkat --}}
            @if ($allowTrust ?? false)
                <div class="flex items-center">
                    <input 
                        id="trust_device" 
                        name="trust_device" 
                        type="checkbox" 
                        value="1"
                        class="h-4 w-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500 cursor-pointer"
                    >
                    <label for="trust_device" class="ml-2 block text-xs text-slate-600 cursor-pointer">
                        {{ __('authentication::messages.trust_device_label') }}
                    </label>
                </div>
            @endif

            {{-- Tombol Submit --}}
            <x-authentication::button type="submit" variant="primary" block="true">
                {{ __('authentication::messages.two_factor_btn') }}
            </x-authentication::button>

            {{-- Switch antara TOTP dan Backup Code --}}
            <div class="text-center pt-2">
                <button 
                    type="button" 
                    @click="recovery = !recovery"
                    class="text-xs font-medium text-blue-600 hover:text-blue-700 hover:underline transition"
                    x-text="recovery ? '{{ __('authentication::messages.two_factor_use_totp') }}' : '{{ __('authentication::messages.two_factor_use_recovery') }}'"
                ></button>
            </div>
        </form>

        {{-- Link Kembali ke Login --}}
        <div class="text-center border-t border-slate-100 pt-3">
            <a href="{{ route('login') }}" class="text-xs text-slate-500 hover:text-slate-700 transition">
                ← {{ __('authentication::messages.back_to_login_arrow') }}
            </a>
        </div>

    </div>

</x-dynamic-component>
