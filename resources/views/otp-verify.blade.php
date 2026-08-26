{{-- 
=============================================================================
HALAMAN VIEW: OTP VERIFY (CLEAN BREEZE STYLE)
Package: mixudev/laravel-authentication
Deskripsi: Halaman verifikasi kode OTP bersih standar Laravel Breeze.
=============================================================================
--}}
@php
    $activeLayout = config('authentication.ui.layout', 'card') === 'split' 
        ? 'authentication::layouts.split' 
        : 'authentication::layouts.card';

    $verifyRoute = Route::has('authentication.otp.verify.perform') 
        ? route('authentication.otp.verify.perform') 
        : (Route::has('otp.verify.perform') ? route('otp.verify.perform') : url('/otp/verify'));

    $sendRoute = Route::has('authentication.otp.send') 
        ? route('authentication.otp.send') 
        : (Route::has('otp.send') ? route('otp.send') : url('/otp/request'));

    $loginRoute = Route::has('authentication.login') 
        ? route('authentication.login') 
        : (Route::has('login') ? route('login') : url('/login'));

    $identifier = $identifier ?? old('identifier', request('identifier', ''));
    $otpLength = (int) config('authentication.features.otp.length', 6);
@endphp

<x-dynamic-component :component="$activeLayout" :title="__('Verifikasi OTP')">
    
    <div class="space-y-4">
        
        <x-authentication::header 
            :title="__('Verifikasi Kode OTP')"
            :subtitle="__('Masukkan 6 digit kode yang telah dikirimkan ke email Anda.')"
        />

        {{-- Flash Alerts --}}
        @if (session('status'))
            <x-authentication::alert type="success" :message="session('status')" />
        @endif

        @if (session('error'))
            <x-authentication::alert type="error" :message="session('error')" />
        @endif

        {{-- Form --}}
        <form method="POST" action="{{ $verifyRoute }}" class="space-y-4" novalidate>
            @csrf

            <input type="hidden" name="identifier" value="{{ $identifier }}">

            {{-- 6-Digit OTP --}}
            <div class="py-2">
                <x-authentication::otp-input 
                    name="code"
                    :length="$otpLength"
                    :autofocus="true"
                />
            </div>

            {{-- Ingat Sesi --}}
            <div class="block">
                <x-authentication::checkbox 
                    name="remember"
                    :checked="true"
                    :label="__('Ingat sesi saya')"
                />
            </div>

            {{-- Tombol Submit --}}
            <div class="pt-2">
                <x-authentication::button type="submit" variant="primary">
                    {{ __('Verifikasi & Masuk') }}
                </x-authentication::button>
            </div>

        </form>

        {{-- Kirim Ulang & Kembali --}}
        <div class="space-y-2 pt-4 border-t border-gray-100 dark:border-gray-700 text-center text-xs text-gray-600 dark:text-gray-400">
            <form method="POST" action="{{ $sendRoute }}" class="inline-block">
                @csrf
                <input type="hidden" name="identifier" value="{{ $identifier }}">
                <span>{{ __('Tidak menerima kode?') }}</span>
                <button type="submit" class="underline hover:text-gray-900 dark:hover:text-gray-100 font-medium ml-1 cursor-pointer bg-transparent border-0 p-0">
                    {{ __('Kirim ulang') }}
                </button>
            </form>

            <div>
                <a href="{{ $loginRoute }}" class="underline hover:text-gray-900 dark:hover:text-gray-100 block pt-1">
                    &larr; {{ __('Kembali ke login biasa') }}
                </a>
            </div>
        </div>

    </div>

</x-dynamic-component>
