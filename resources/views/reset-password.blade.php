{{-- 
=============================================================================
HALAMAN VIEW: RESET PASSWORD
Package: mixudev/laravel-authentication
Deskripsi: Halaman reset password dengan alert di atas form dan auto-dismiss 3 detik.
=============================================================================
--}}
@php
    $activeLayout = config('authentication.ui.layout', 'card') === 'split' 
        ? 'authentication::layouts.split' 
        : 'authentication::layouts.card';

    $updateRoute = Route::has('authentication.password.update') 
        ? route('authentication.password.update') 
        : (Route::has('password.update') ? route('password.update') : url('/reset-password'));

    $loginRoute = Route::has('authentication.login') 
        ? route('authentication.login') 
        : (Route::has('login') ? route('login') : url('/login'));
@endphp

<x-dynamic-component :component="$activeLayout" :title="__('authentication::messages.reset_title')">
    
    <div class="space-y-4">
        
        <x-authentication::header 
            :title="__('authentication::messages.reset_title')"
            :subtitle="__('authentication::messages.reset_subtitle')"
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

        <form method="POST" action="{{ $updateRoute }}" class="space-y-4" novalidate>
            @csrf

            <input type="hidden" name="token" value="{{ $token ?? request()->route('token') }}">

            <x-authentication::input 
                name="email"
                type="email"
                :label="__('Alamat Email')"
                :value="old('email', $email ?? request()->email)"
                :required="true"
                autocomplete="email"
            />

            <x-authentication::input 
                name="password"
                type="password"
                :label="__('authentication::messages.new_password_label')"
                :placeholder="__('authentication::messages.new_password_ph')"
                :required="true"
                autocomplete="new-password"
                :autofocus="true"
            />

            <x-authentication::input 
                name="password_confirmation"
                type="password"
                :label="__('authentication::messages.confirm_password_label')"
                :placeholder="__('authentication::messages.confirm_password_ph')"
                :required="true"
                autocomplete="new-password"
            />

            <div class="pt-2">
                <x-authentication::button type="submit" variant="primary">
                    {{ __('authentication::messages.reset_btn') }}
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
