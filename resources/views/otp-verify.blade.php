{{-- 
=============================================================================
HALAMAN VIEW: OTP VERIFY
Package: mixudev/laravel-authentication
Deskripsi: Halaman verifikasi kode OTP dengan dukungan 2 bahasa.
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

<x-dynamic-component :component="$activeLayout" :title="__('authentication::messages.otp_verify_title')">
    
    <div class="space-y-4">
        
        <x-authentication::header 
            :title="__('authentication::messages.otp_verify_title')"
            :subtitle="__('authentication::messages.otp_verify_subtitle')"
        />

        @if (session('status'))
            <x-authentication::alert type="success" :message="session('status')" />
        @endif
        @if (session('error'))
            <x-authentication::alert type="error" :message="session('error')" />
        @endif

        <form method="POST" action="{{ $verifyRoute }}" class="space-y-4" novalidate>
            @csrf

            <input type="hidden" name="identifier" value="{{ $identifier }}">

            {{-- Kotak OTP 6 Digit --}}
            <div class="py-2">
                <x-authentication::otp-input 
                    name="code"
                    :length="$otpLength"
                    :autofocus="true"
                />
            </div>

            {{-- Alert Error --}}
            @if ($errors->any())
                <x-authentication::alert type="error">
                    <span class="flex items-center gap-1.5">
                        <svg class="w-3.5 h-3.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        {{ $errors->first() }}
                    </span>
                </x-authentication::alert>
            @endif

            <div class="block pt-1">
                <x-authentication::checkbox 
                    name="remember"
                    :checked="true"
                    :label="__('authentication::messages.remember_device')"
                />
            </div>

            <div class="pt-2">
                <x-authentication::button type="submit" variant="primary">
                    {{ __('authentication::messages.otp_verify_btn') }}
                </x-authentication::button>
            </div>

        </form>

        <div class="space-y-2 pt-4 border-t border-zinc-100 dark:border-zinc-800 text-center text-xs">
            <form method="POST" action="{{ $sendRoute }}" class="inline-block">
                @csrf
                <input type="hidden" name="identifier" value="{{ $identifier }}">
                <span class="auth-subtext">{{ __('authentication::messages.otp_resend_hint') }}</span>
                <button type="submit" class="auth-link font-medium hover:underline ml-1 cursor-pointer bg-transparent border-0 p-0">
                    {{ __('authentication::messages.otp_resend_btn') }}
                </button>
            </form>

            <div>
                <a href="{{ $loginRoute }}" class="auth-link hover:underline block pt-1">
                    &larr; {{ __('authentication::messages.back_to_login') }}
                </a>
            </div>
        </div>

    </div>

</x-dynamic-component>
