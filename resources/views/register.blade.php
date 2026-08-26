{{-- 
=============================================================================
HALAMAN VIEW: REGISTER
Package: mixudev/laravel-authentication
Deskripsi: Halaman registrasi dengan dukungan 2 bahasa (Indonesia / Inggris).
=============================================================================
--}}
@php
    $activeLayout = config('authentication.ui.layout', 'card') === 'split' 
        ? 'authentication::layouts.split' 
        : 'authentication::layouts.card';

    $registerPerformRoute = Route::has('register.perform') 
        ? route('register.perform') 
        : (Route::has('authentication.register') ? route('authentication.register') : url('/register'));

    $loginRoute = Route::has('login') 
        ? route('login') 
        : (Route::has('authentication.login') ? route('authentication.login') : url('/login'));
@endphp

<x-dynamic-component :component="$activeLayout" :title="__('authentication::messages.register_title')">
    
    <div class="space-y-4">
        
        <x-authentication::header 
            :title="__('authentication::messages.register_title')"
            :subtitle="__('authentication::messages.register_subtitle')"
        />

        @if (session('status'))
            <x-authentication::alert type="success" :message="session('status')" />
        @endif
        @if (session('error'))
            <x-authentication::alert type="error" :message="session('error')" />
        @endif

        <x-authentication::social-buttons />

        @if (config('authentication.features.social.enabled', false))
            <x-authentication::divider :label="__('authentication::messages.divider')" />
        @endif

        <form method="POST" action="{{ $registerPerformRoute }}" class="space-y-4" novalidate>
            @csrf

            <x-authentication::input 
                name="name"
                :label="__('Nama Lengkap')"
                :placeholder="__('Nama lengkap Anda')"
                :required="true"
                autocomplete="name"
                :autofocus="true"
            />

            <x-authentication::input 
                name="email"
                type="email"
                :label="__('Alamat Email')"
                :placeholder="__('nama@domain.com')"
                :required="true"
                autocomplete="email"
            />

            @if (in_array(config('authentication.strategies.active'), ['username_password', 'username_or_email']))
                <x-authentication::input 
                    name="username"
                    :label="__('Username')"
                    :placeholder="__('Pilih username unik')"
                    autocomplete="username"
                />
            @endif

            <x-authentication::input 
                name="password"
                type="password"
                :label="__('authentication::messages.password_label')"
                :placeholder="__('Minimal 8 karakter')"
                :required="true"
                autocomplete="new-password"
            />

            <x-authentication::input 
                name="password_confirmation"
                type="password"
                :label="__('Konfirmasi Kata Sandi')"
                :placeholder="__('Ulangi kata sandi')"
                :required="true"
                autocomplete="new-password"
            />

            <div class="block pt-1">
                <x-authentication::checkbox name="terms" :required="true">
                    <span class="auth-subtext text-xs">
                        {{ __('authentication::messages.terms_agree') }} 
                        <a href="#" class="auth-link underline">{{ __('authentication::messages.terms_label') }}</a>.
                    </span>
                </x-authentication::checkbox>
            </div>

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
                    {{ __('authentication::messages.register_btn') }}
                </x-authentication::button>
            </div>

        </form>

        <div class="pt-4 border-t border-zinc-100 dark:border-zinc-800 text-center text-xs">
            <p class="auth-subtext">
                {{ __('authentication::messages.already_account') }}
                <a href="{{ $loginRoute }}" class="auth-link font-medium hover:underline ml-1">
                    {{ __('authentication::messages.login_here') }} &rarr;
                </a>
            </p>
        </div>

    </div>

</x-dynamic-component>
