{{-- 
=============================================================================
HALAMAN VIEW: LOGIN
Package: mixudev/laravel-authentication
Deskripsi: Halaman login bersih dengan alert di bawah field dan dukungan 2 bahasa.
=============================================================================
--}}
@php
    $activeLayout = config('authentication.ui.layout', 'card') === 'split' 
        ? 'authentication::layouts.split' 
        : 'authentication::layouts.card';

    /* Resolusi rute secara fleksibel agar kompatibel dengan berbagai konfigurasi host */
    $loginPerformRoute = Route::has('login.perform') 
        ? route('login.perform') 
        : (Route::has('authentication.login') ? route('authentication.login') : url('/login'));

    $forgotPasswordRoute = Route::has('password.request') 
        ? route('password.request') 
        : (Route::has('authentication.password.request') ? route('authentication.password.request') : url('/forgot-password'));

    $otpRequestRoute = Route::has('otp.request.form') 
        ? route('otp.request.form') 
        : (Route::has('authentication.otp.request') ? route('authentication.otp.request') : url('/otp/login'));

    $registerRoute = Route::has('register') 
        ? route('register') 
        : (Route::has('authentication.register') ? route('authentication.register') : url('/register'));
@endphp

<x-dynamic-component :component="$activeLayout" :title="__('authentication::messages.sign_in')">
    
    <div class="space-y-4">
        
        {{-- Header Halaman --}}
        <x-authentication::header 
            :title="__('authentication::messages.sign_in')"
            :subtitle="__('authentication::messages.sign_in_subtitle')"
        />

        {{-- Notifikasi Status Sukses (misal: setelah logout) --}}
        @if (session('status'))
            <x-authentication::alert type="success" :message="session('status')" />
        @endif

        {{-- Tombol Social Login (Google / GitHub) --}}
        <x-authentication::social-buttons />

        @if (config('authentication.features.social.enabled', false))
            <x-authentication::divider :label="__('authentication::messages.divider')" />
        @endif

        {{-- Formulir Login Utama --}}
        <form method="POST" action="{{ $loginPerformRoute }}" class="space-y-4" novalidate>
            @csrf

            {{-- Identifier (Email / Username) --}}
            <x-authentication::input 
                name="identifier"
                :label="__('authentication::messages.identifier_label')"
                :placeholder="__('authentication::messages.identifier_placeholder')"
                :required="true"
                autocomplete="username"
                :autofocus="true"
            />

            {{-- Password dengan link lupa password --}}
            <x-authentication::input 
                name="password"
                type="password"
                :label="__('authentication::messages.password_label')"
                :placeholder="__('authentication::messages.password_placeholder')"
                :required="true"
                autocomplete="current-password"
            >
                @if (config('authentication.features.forgot_password.enabled', true))
                    <x-slot:labelRight>
                        <a href="{{ $forgotPasswordRoute }}" class="auth-link text-xs hover:underline">
                            {{ __('authentication::messages.forgot_password') }}
                        </a>
                    </x-slot:labelRight>
                @endif
            </x-authentication::input>

            {{-- Alert Error Kredensial — Tampil di bawah field password, bukan di atas form --}}
            @php
                $credentialError = $errors->first('credentials') 
                    ?: $errors->first('identifier')
                    ?: $errors->first('password')
                    ?: session('error');
            @endphp
            @if ($credentialError)
                <x-authentication::alert type="error">
                    <span class="flex items-center gap-1.5">
                        <svg class="w-3.5 h-3.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        {{ $credentialError }}
                    </span>
                </x-authentication::alert>
            @elseif ($errors->any())
                <x-authentication::alert type="error">
                    <span class="flex items-center gap-1.5">
                        <svg class="w-3.5 h-3.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        {{ $errors->first() }}
                    </span>
                </x-authentication::alert>
            @endif

            {{-- Checkbox Ingat Saya --}}
            <div class="block pt-1">
                <x-authentication::checkbox 
                    name="remember"
                    :label="__('authentication::messages.remember_me')"
                />
            </div>

            {{-- Tombol Submit --}}
            <div class="pt-2">
                <x-authentication::button type="submit" variant="primary">
                    {{ __('authentication::messages.sign_in_btn') }}
                </x-authentication::button>
            </div>

        </form>

        {{-- Link Alternatif (OTP & Registrasi) --}}
        <div class="space-y-2 pt-4 border-t border-zinc-100 dark:border-zinc-800 text-center text-xs">
            @if (config('authentication.features.otp.enabled', true))
                <div>
                    <a href="{{ $otpRequestRoute }}" class="auth-link hover:underline">
                        {{ __('authentication::messages.sign_in_otp') }}
                    </a>
                </div>
            @endif

            @if (config('authentication.features.registration.enabled', true))
                <div class="auth-subtext">
                    <p>
                        {{ __('authentication::messages.no_account') }}
                        <a href="{{ $registerRoute }}" class="auth-link font-medium hover:underline ml-1">
                            {{ __('authentication::messages.register_now') }} &rarr;
                        </a>
                    </p>
                </div>
            @endif
        </div>

    </div>

</x-dynamic-component>
