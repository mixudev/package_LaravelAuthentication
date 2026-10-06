{{--
=============================================================================
HALAMAN VIEW: LOGIN
Package: mixudev/laravel-authentication
Deskripsi: Halaman login bersih dengan CAPTCHA adaptif (muncul setelah N kali gagal).
=============================================================================
--}}
@php
    use Vendor\LaravelAuthentication\Services\Security\CaptchaService;

    $activeLayout = config('authentication.ui.layout', 'card') === 'split'
        ? 'authentication::layouts.split'
        : 'authentication::layouts.card';

    $loginPerformRoute = Route::has('authentication.login.perform')
        ? route('authentication.login.perform')
        : (Route::has('authentication.login') ? route('authentication.login') : url('/login'));

    $forgotPasswordRoute = Route::has('authentication.password.request')
        ? route('authentication.password.request')
        : (Route::has('authentication.password.request') ? route('authentication.password.request') : url('/forgot-password'));

    $otpRequestRoute = Route::has('authentication.otp.request.form')
        ? route('authentication.otp.request.form')
        : (Route::has('authentication.otp.request') ? route('authentication.otp.request') : url('/otp/login'));

    $registerRoute = Route::has('authentication.register')
        ? route('authentication.register')
        : (Route::has('authentication.register') ? route('authentication.register') : url('/register'));

    $credentialError = $errors->first('credentials')
        ?: $errors->first('identifier')
        ?: $errors->first('password')
        ?: session('error');

    // CAPTCHA: Tentukan apakah perlu tampil di request ini
    /** @var CaptchaService $captchaService */
    $captchaService   = app(CaptchaService::class);
    $captchaDriver    = config('authentication.security.captcha.driver', 'turnstile');
    $captchaSiteKey   = config('authentication.security.captcha.site_key', '');
    $showCaptcha      = $captchaService->isEnabled()
        && $captchaService->shouldShowCaptcha(old('identifier'), request()->ip());
@endphp

<x-dynamic-component :component="$activeLayout" :title="__('authentication::messages.sign_in')">

    <div class="space-y-4">

        {{-- Header Halaman --}}
        <x-authentication::header
            :title="__('authentication::messages.sign_in')"
            :subtitle="__('authentication::messages.sign_in_subtitle')"
        />

        {{--
            Alert Notifikasi — Tampil di atas form, hilang otomatis dalam 3 detik.
            Sukses: setelah logout / redirect. Error: kredensial salah.
            Throttle/Lockout: countdown timer live yang disable tombol submit.
        --}}
        @if (session('status'))
            <x-authentication::feedback.alert type="success" :autodismiss="true" :message="session('status')" />
        @endif

        {{-- @if (session('auth_retry_after'))
            <x-authentication::feedback.countdown-alert
                type="error"
                :retryAfter="session('auth_retry_after')"
                submitButton="#login-submit-btn"
            />
        @elseif ($credentialError)
            <x-authentication::feedback.alert type="error" :message="$credentialError" />
        @elseif ($errors->any())
            <x-authentication::feedback.alert type="error" :message="$errors->first()" />
        @endif --}}

        {{-- Tombol Login Alternatif: Social (Google/GitHub sebelahan) + Passkey --}}
        @php
            $hasSocial  = config('authentication.features.social.enabled', false);
            $hasPasskey = config('authentication.features.passkey.enabled', true);
        @endphp

        @if ($hasSocial || $hasPasskey)
            <div class="space-y-2.5">
                {{-- Google (kiri) + GitHub (kanan) — 2 kolom --}}
                <x-authentication::social-buttons />

                {{-- Login with Passkey — full width di bawah --}}
                @if ($hasPasskey)
                    <x-authentication::security.passkey-button />
                @endif
            </div>

            <x-authentication::divider :label="__('authentication::messages.divider')" />
        @endif

        {{-- Formulir Login Utama --}}
        <form method="POST" action="{{ $loginPerformRoute }}" class="space-y-4" novalidate
              x-data="{ submitting: false }"
              @submit="submitting = true">
            @csrf

            {{-- Identifier (Email / Username) --}}
            <x-authentication::forms.input
                name="identifier"
                :label="__('authentication::messages.identifier_label')"
                :placeholder="__('authentication::messages.identifier_placeholder')"
                :required="true"
                autocomplete="username"
                :autofocus="true"
            />

            {{-- Password dengan link lupa password --}}
            <x-authentication::forms.input
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
            </x-authentication::forms.input>

            {{-- Checkbox Ingat Saya --}}
            <div class="block pt-1">
                <x-authentication::forms.checkbox
                    name="remember"
                    :label="__('authentication::messages.remember_me')"
                />
            </div>

            {{--
                CAPTCHA Widget Adaptif
                Muncul otomatis setelah trigger_after_failed_attempts kali gagal login.
                Cloudflare Turnstile: field "cf-turnstile-response"
                Google reCAPTCHA:    field "g-recaptcha-response"
                hCaptcha:            field "h-captcha-response"
            --}}
            @if ($showCaptcha && !empty($captchaSiteKey))
                <div class="pt-1">
                    @if ($captchaDriver === 'turnstile')
                        <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
                        <div class="cf-turnstile" data-sitekey="{{ $captchaSiteKey }}" data-theme="light"></div>
                        @if ($errors->has('cf-turnstile-response'))
                            <p class="text-xs text-red-500 mt-1">{{ $errors->first('cf-turnstile-response') }}</p>
                        @endif

                    @elseif ($captchaDriver === 'recaptcha_v2')
                        <script src="https://www.google.com/recaptcha/api.js" async defer></script>
                        <div class="g-recaptcha" data-sitekey="{{ $captchaSiteKey }}"></div>
                        @if ($errors->has('g-recaptcha-response'))
                            <p class="text-xs text-red-500 mt-1">{{ $errors->first('g-recaptcha-response') }}</p>
                        @endif

                    @elseif ($captchaDriver === 'recaptcha_v3')
                        <script src="https://www.google.com/recaptcha/api.js?render={{ $captchaSiteKey }}" async defer></script>
                        {{-- reCAPTCHA v3 tidak tampil secara visual, token dikirim via hidden field --}}
                        <input type="hidden" name="g-recaptcha-response" id="g-recaptcha-response-v3">
                        <script>
                            document.addEventListener('DOMContentLoaded', function() {
                                if (window.grecaptcha) {
                                    grecaptcha.ready(function() {
                                        grecaptcha.execute('{{ $captchaSiteKey }}', {action: 'login'}).then(function(token) {
                                            document.getElementById('g-recaptcha-response-v3').value = token;
                                        });
                                    });
                                }
                            });
                        </script>

                    @elseif ($captchaDriver === 'hcaptcha')
                        <script src="https://js.hcaptcha.com/1/api.js" async defer></script>
                        <div class="h-captcha" data-sitekey="{{ $captchaSiteKey }}"></div>
                        @if ($errors->has('h-captcha-response'))
                            <p class="text-xs text-red-500 mt-1">{{ $errors->first('h-captcha-response') }}</p>
                        @endif
                    @endif
                </div>
            @endif

            {{-- Tombol Submit --}}
            <div class="pt-2">
                <x-authentication::forms.button id="login-submit-btn" type="submit" variant="primary">
                    {{ __('authentication::messages.sign_in_btn') }}
                </x-authentication::forms.button>
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
