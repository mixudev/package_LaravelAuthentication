{{-- 
=============================================================================
HALAMAN VIEW: FORGOT PASSWORD
Package: mixudev/laravel-authentication
Deskripsi: Halaman lupa password dengan alert di atas form dan auto-dismiss 3 detik.
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

        {{-- Alert di atas form, hilang otomatis dalam 3 detik --}}
        @if (session('status'))
            <x-authentication::alert type="success" :autodismiss="true" :message="session('status')" />
        @endif
        @if (session('error'))
            <x-authentication::alert type="error" :autodismiss="true" :message="session('error')" />
        @elseif ($errors->any())
            <x-authentication::alert type="error" :autodismiss="true" :message="$errors->first()" />
        @endif

        <form method="POST" action="{{ $emailRoute }}" class="space-y-4" novalidate>
            @csrf

            <x-authentication::input 
                name="email"
                type="email"
                :label="__('authentication::messages.email_label')"
                :placeholder="__('authentication::messages.email_placeholder')"
                :required="true"
                autocomplete="email"
                :autofocus="true"
            />

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
