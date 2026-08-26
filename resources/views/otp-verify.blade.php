{{-- 
=============================================================================
HALAMAN VIEW: OTP VERIFY (VERIFIKASI KODE OTP)
Package: mixudev/laravel-authentication
Deskripsi: Halaman input 6-digit OTP bersegmen dengan fitur kirim ulang,
           ingat sesi, serta penanganan auto-sync input form.
=============================================================================
--}}
@props([
    'identifier' => request()->get('identifier', session('auth_otp_identifier', '')),
])

@php
    $activeLayout = config('authentication.ui.layout', 'split') === 'card' 
        ? 'authentication::layouts.card' 
        : 'authentication::layouts.split';

    $verifyRoute = Route::has('authentication.otp.verify') 
        ? route('authentication.otp.verify') 
        : (Route::has('otp.verify') ? route('otp.verify') : url('/otp/verify'));

    $sendRoute = Route::has('authentication.otp.send') 
        ? route('authentication.otp.send') 
        : (Route::has('otp.send') ? route('otp.send') : url('/otp/request'));

    $loginRoute = Route::has('authentication.login') 
        ? route('authentication.login') 
        : (Route::has('login') ? route('login') : url('/login'));

    $otpLength = (int) config('authentication.features.otp.length', 6);
@endphp

<x-dynamic-component :component="$activeLayout" :title="__('Verifikasi Kode OTP')">
    
    <div class="space-y-6">
        
        {{-- Header Formulir --}}
        <x-authentication::header 
            :title="__('Masukkan Kode OTP')"
            :subtitle="__('Kode verifikasi telah dikirimkan ke ') . ($identifier ? ' ' . $identifier : __('email terdaftar Anda')) . '.'"
            :badge="__('TWO-STEP VERIFICATION')"
        />

        {{-- Notifikasi Status / Error --}}
        @if (session('status'))
            <x-authentication::alert type="success" :message="session('status')" />
        @endif

        @if (session('error'))
            <x-authentication::alert type="error" :message="session('error')" />
        @endif

        {{-- Formulir Verifikasi OTP --}}
        <form method="POST" action="{{ $verifyRoute }}" class="space-y-5" novalidate>
            @csrf

            {{-- Identifier Hidden --}}
            <input type="hidden" name="identifier" value="{{ $identifier }}">

            {{-- Label & Input 6-Digit Bersegmen --}}
            <div class="space-y-2">
                <label class="block text-xs font-medium font-mono-code uppercase tracking-wider text-slate-300">
                    {{ __('KODE VERIFIKASI (:length DIGIT)', ['length' => $otpLength]) }}
                    <span class="text-amber-400 font-bold">*</span>
                </label>

                <x-authentication::otp-input 
                    name="code"
                    :length="$otpLength"
                    :autofocus="true"
                />
            </div>

            {{-- Opsi Ingat Sesi --}}
            <div class="flex items-center justify-between pt-1">
                <x-authentication::checkbox 
                    name="remember"
                    :checked="true"
                    :label="__('Ingat sesi saya pada perangkat ini')"
                />
            </div>

            {{-- Tombol Verifikasi & Masuk --}}
            <x-authentication::button type="submit" variant="primary" class="mt-2">
                <x-slot:icon>
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                    </svg>
                </x-slot:icon>
                {{ __('Verifikasi & Masuk ke Sistem') }}
            </x-authentication::button>

        </form>

        {{-- Kirim Ulang Kode & Kembali ke Login --}}
        <div class="space-y-3 pt-3 border-t border-slate-850 text-center text-xs text-slate-400">
            <form method="POST" action="{{ $sendRoute }}" class="inline-block">
                @csrf
                <input type="hidden" name="identifier" value="{{ $identifier }}">
                <span class="text-slate-400">{{ __('Tidak menerima kode?') }}</span>
                <button type="submit" class="text-amber-400 hover:text-amber-300 font-medium ml-1 underline cursor-pointer bg-transparent border-0 p-0 focus:outline-none">
                    {{ __('Kirim ulang kode OTP') }}
                </button>
            </form>

            <div>
                <a href="{{ $loginRoute }}" class="text-slate-400 hover:text-slate-200 font-mono-code transition-colors block pt-1">
                    &larr; {{ __('Kembali ke halaman masuk biasa') }}
                </a>
            </div>
        </div>

    </div>

</x-dynamic-component>
