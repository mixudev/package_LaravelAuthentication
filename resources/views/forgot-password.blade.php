{{-- 
=============================================================================
HALAMAN VIEW: FORGOT PASSWORD
Package: mixudev/laravel-authentication
Deskripsi: Halaman lupa password dengan dukungan 2 bahasa.
=============================================================================
--}}
@php
    $activeLayout = config('authentication.ui.layout', 'card') === 'split' 
        ? 'authentication::layouts.split' 
        : 'authentication::layouts.card';

    $emailRoute = Route::has('authentication.password.email') 
        ? route('authentication.password.email') 
        : (Route::has('password.email') ? route('password.email') : url('/forgot-password'));

    $loginRoute = Route::has('authentication.login') 
        ? route('authentication.login') 
        : (Route::has('login') ? route('login') : url('/login'));
@endphp

<x-dynamic-component :component="$activeLayout" :title="__('authentication::messages.forgot_title')">
    
    <div class="space-y-4">
        
        <x-authentication::header 
            :title="__('authentication::messages.forgot_title')"
            :subtitle="__('authentication::messages.forgot_subtitle')"
        />

        @if (session('status'))
            <x-authentication::alert type="success" :message="session('status')" />
        @endif
        @if (session('error'))
            <x-authentication::alert type="error" :message="session('error')" />
        @endif

        <form method="POST" action="{{ $emailRoute }}" class="space-y-4" novalidate>
            @csrf

            <x-authentication::input 
                name="email"
                type="email"
                :label="__('Alamat Email')"
                :placeholder="__('nama@domain.com')"
                :required="true"
                autocomplete="email"
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
                    {{ __('authentication::messages.forgot_btn') }}
                </x-authentication::button>
            </div>

        </form>

        <div class="pt-4 border-t border-zinc-100 dark:border-zinc-800 text-center text-xs">
            <a href="{{ $loginRoute }}" class="auth-link hover:underline">
                &larr; {{ __('authentication::messages.back_to_login_arrow') }}
            </a>
        </div>

    </div>

</x-dynamic-component>
