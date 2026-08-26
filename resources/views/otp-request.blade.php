{{-- 
=============================================================================
HALAMAN VIEW: OTP REQUEST
Package: mixudev/laravel-authentication
Deskripsi: Halaman permintaan OTP dengan dukungan 2 bahasa.
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

        @if (session('status'))
            <x-authentication::alert type="success" :message="session('status')" />
        @endif
        @if (session('error'))
            <x-authentication::alert type="error" :message="session('error')" />
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
