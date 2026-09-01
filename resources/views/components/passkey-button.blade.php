{{-- 
=============================================================================
KOMPONEN: TOMBOL LOGIN DENGAN PASSKEY (FIDO2 / WEBAUTHN)
Package: mixudev/laravel-authentication
Deskripsi: Tombol autentikasi biometrik standar W3C WebAuthn — full-width, icon kiri, teks kanan.
=============================================================================
--}}
@php
    $isPasskeyEnabled = config('authentication.features.passkey.enabled', true);
    $optionsRoute = Route::has('passkey.login.options')
        ? route('passkey.login.options')
        : (Route::has('authentication.passkey.login.options') ? route('authentication.passkey.login.options') : url('/auth/passkey/login-options'));
    $loginRoute = Route::has('passkey.login')
        ? route('passkey.login')
        : (Route::has('authentication.passkey.login') ? route('authentication.passkey.login') : url('/auth/passkey/login'));
@endphp

@if ($isPasskeyEnabled)
<div class="w-full">
    <button
        type="button"
        id="btn-login-passkey"
        onclick="window.startPasskeyLogin()"
        class="auth-btn-social auth-btn-passkey"
        aria-label="{{ __('authentication::messages.passkey_btn') }}"
    >
        {{-- Fingerprint / Passkey icon --}}
        <span class="auth-btn-icon" id="passkey-btn-icon-wrap">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M12 10a2 2 0 0 0-2 2c0 1.02-.1 2.51-.26 4"/>
                <path d="M14 13.12c0 2.38 0 6.38-1 8.88"/>
                <path d="M17.29 21.02c.12-.6.43-2.3.5-3.02"/>
                <path d="M2 12a10 10 0 0 1 18-6"/>
                <path d="M2 16h.01"/>
                <path d="M21.8 16c.2-2 .131-5.354 0-6"/>
                <path d="M5 19.5C5.5 18 6 15 6 12a6 6 0 0 1 .34-2"/>
                <path d="M8.65 22c.21-.66.45-1.32.57-2"/>
                <path d="M9 6.8a6 6 0 0 1 9 5.2v2"/>
            </svg>
        </span>

        {{-- Loading spinner (hidden by default) --}}
        <span class="auth-btn-icon" id="passkey-btn-spinner" style="display: none;">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" class="animate-spin">
                <circle cx="12" cy="12" r="10" stroke-opacity="0.25"/>
                <path d="M12 2a10 10 0 0 1 10 10" stroke-opacity="0.85"/>
            </svg>
        </span>

        <span class="auth-btn-label" id="passkey-btn-text">Login with Passkey</span>
    </button>
</div>

@push('scripts')
<script>
    (function() {
        function bufferToBase64Url(buffer) {
            var bytes = new Uint8Array(buffer);
            var binary = '';
            for (var i = 0; i < bytes.byteLength; i++) binary += String.fromCharCode(bytes[i]);
            return btoa(binary).replace(/\+/g, '-').replace(/\//g, '_').replace(/=/g, '');
        }

        function base64UrlToBuffer(base64url) {
            var padding = '='.repeat((4 - base64url.length % 4) % 4);
            var base64 = (base64url + padding).replace(/\-/g, '+').replace(/_/g, '/');
            var rawData = atob(base64);
            var buffer = new Uint8Array(rawData.length);
            for (var i = 0; i < rawData.length; ++i) buffer[i] = rawData.charCodeAt(i);
            return buffer.buffer;
        }

        function setLoading(state) {
            var icon = document.getElementById('passkey-btn-icon-wrap');
            var spinner = document.getElementById('passkey-btn-spinner');
            var text = document.getElementById('passkey-btn-text');
            var btn = document.getElementById('btn-login-passkey');
            if (!icon || !spinner || !btn) return;
            if (state) {
                icon.style.display = 'none';
                spinner.style.display = 'flex';
                if (text) text.textContent = 'Memverifikasi...';
                btn.disabled = true;
            } else {
                icon.style.display = 'flex';
                spinner.style.display = 'none';
                if (text) text.textContent = 'Login with Passkey';
                btn.disabled = false;
            }
        }

        window.startPasskeyLogin = async function() {
            if (!window.PublicKeyCredential) {
                alert('{{ __("authentication::messages.passkey_not_supported") }}');
                return;
            }
            try {
                setLoading(true);

                var identifierInput = document.querySelector('input[name="identifier"]') || document.querySelector('input[name="email"]');
                var identifier = identifierInput ? identifierInput.value.trim() : '';
                var url = '{{ $optionsRoute }}' + (identifier ? '?identifier=' + encodeURIComponent(identifier) : '');

                var optRes = await fetch(url, {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
                });
                if (!optRes.ok) throw new Error('Gagal mengambil opsi autentikasi Passkey.');
                var options = await optRes.json();

                options.challenge = base64UrlToBuffer(options.challenge);
                if (options.allowCredentials && Array.isArray(options.allowCredentials)) {
                    options.allowCredentials = options.allowCredentials.map(function(c) {
                        return { id: base64UrlToBuffer(c.id), type: c.type || 'public-key', transports: c.transports };
                    });
                }

                var credential = await navigator.credentials.get({ publicKey: options });
                if (!credential) throw new Error('Tidak ada kredensial yang dipilih.');

                var assertionPayload = {
                    id: credential.id,
                    rawId: bufferToBase64Url(credential.rawId),
                    type: credential.type,
                    response: {
                        clientDataJSON: bufferToBase64Url(credential.response.clientDataJSON),
                        authenticatorData: bufferToBase64Url(credential.response.authenticatorData),
                        signature: bufferToBase64Url(credential.response.signature),
                        userHandle: credential.response.userHandle ? bufferToBase64Url(credential.response.userHandle) : null
                    }
                };

                var csrfMeta = document.querySelector('meta[name="csrf-token"]');
                var csrfToken = csrfMeta ? csrfMeta.getAttribute('content') : '';

                var loginRes = await fetch('{{ $loginRoute }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: JSON.stringify(assertionPayload)
                });

                var loginData = await loginRes.json();
                if (loginRes.ok && loginData.status === 'success') {
                    window.location.href = loginData.redirect || '/dashboard';
                } else {
                    alert(loginData.message || '{{ __("authentication::messages.passkey_failed") }}');
                }
            } catch (err) {
                if (err.name !== 'NotAllowedError') {
                    alert(err.message || '{{ __("authentication::messages.passkey_failed") }}');
                }
            } finally {
                setLoading(false);
            }
        };

        // WebAuthn Conditional UI — Autofill support
        document.addEventListener('DOMContentLoaded', function() {
            if (window.PublicKeyCredential && PublicKeyCredential.isConditionalMediationAvailable) {
                PublicKeyCredential.isConditionalMediationAvailable().then(function(available) {
                    if (available) {
                        var idInput = document.querySelector('input[name="identifier"]');
                        if (idInput && !idInput.autocomplete.includes('webauthn')) {
                            idInput.autocomplete += ' webauthn';
                        }
                    }
                });
            }
        });
    })();
</script>
@endpush
@endif
