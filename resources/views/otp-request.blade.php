{{-- 
=============================================================================
HALAMAN VIEW: OTP REQUEST
Package: mixudev/laravel-authentication
Deskripsi: Halaman permintaan OTP dengan alert di atas form dan auto-dismiss 3 detik.
=============================================================================
--}}
@php
    $activeLayout = config('authentication.ui.layout', 'card') === 'split' 
        ? 'authentication::layouts.split' 
        : 'authentication::layouts.card';

    $sendRoute = Route::has('authentication.otp.send') 
        ? route('authentication.otp.send') 
        : (Route::has('otp.send') ? route('otp.send') : url('/otp/request'));

    $loginRoute = Route::has('authentication.login') 
        ? route('authentication.login') 
        : (Route::has('login') ? route('login') : url('/login'));
@endphp

<x-dynamic-component :component="$activeLayout" :title="__('authentication::messages.otp_request_title')">
    
    <div class="space-y-4">
        
        <x-authentication::header 
            :title="__('authentication::messages.otp_request_title')"
            :subtitle="__('authentication::messages.otp_request_subtitle')"
        />

        {{-- Alert di atas form, hilang otomatis dalam 3 detik --}}
        @if (session('status'))
            <x-authentication::alert type="success" :autodismiss="true" :message="session('status')" />
        @endif
        @if (session('error'))
            <x-authentication::alert type="error" :autodismiss="true" :message="session('error')" />
        @elseif ($errors->any())
            <x-authentication::alert type="error" :autodismiss="true" :message="$errors->first()" />
        @endif

        <form method="POST" action="{{ $sendRoute }}" class="space-y-4" novalidate>
            @csrf

            <x-authentication::input 
                name="identifier"
                :label="__('authentication::messages.identifier_label')"
                :placeholder="__('authentication::messages.identifier_placeholder')"
                :required="true"
                autocomplete="username"
                :autofocus="true"
            />

            <div class="pt-2">
                <x-authentication::button type="submit" variant="primary">
                    {{ __('authentication::messages.otp_request_btn') }}
                </x-authentication::button>
            </div>

        </form>

        <div class="pt-4 border-t border-zinc-100 dark:border-zinc-800 text-center text-xs">
            <a href="{{ $loginRoute }}" class="auth-link hover:underline">
                &larr; {{ __('authentication::messages.back_to_login') }}
            </a>
        </div>

    </div>

</x-dynamic-component>
