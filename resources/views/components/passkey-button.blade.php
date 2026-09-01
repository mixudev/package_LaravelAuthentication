{{-- 
=============================================================================
KOMPONEN: TOMBOL LOGIN DENGAN PASSKEY (FIDO2 / WEBAUTHN)
Package: mixudev/laravel-authentication
Deskripsi: Tombol autentikasi biometrik standar W3C WebAuthn tanpa password.
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

    $btnBase = 'flex items-center justify-center gap-2.5 w-full rounded-lg border border-gray-300 dark:border-zinc-600 bg-white dark:bg-zinc-900 text-gray-800 dark:text-zinc-100 px-4 py-2.5 text-sm font-medium transition-all duration-150 cursor-pointer no-underline select-none shadow-xs hover:bg-gray-50 dark:hover:bg-zinc-800 hover:border-gray-400 dark:hover:border-zinc-500 focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-500 focus-visible:ring-offset-1 active:scale-[0.99]';
@endphp

@if ($isPasskeyEnabled)
<div class="w-full" id="auth-passkey-container">
    <button 
        type="button" 
        id="btn-login-passkey"
        onclick="window.startPasskeyLogin()"
        class="{{ $btnBase }}"
        aria-label="Login with Passkey"
    >
        {{-- Biometric / Fingerprint / Passkey SVG Icon --}}
        <svg id="passkey-icon-svg" class="w-4 h-4 flex-shrink-0 text-blue-600 dark:text-blue-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
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

        {{-- Loading Spinner (Hidden by default) --}}
        <svg id="passkey-spinner-svg" class="w-4 h-4 flex-shrink-0 animate-spin text-blue-600 dark:text-blue-400" style="display: none;" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
        </svg>

        <span id="passkey-btn-text">Login with Passkey</span>
    </button>
</div>

@push('scripts')
<script>
    (function() {
        function bufferToBase64Url(buffer) {
            var bytes = new Uint8Array(buffer);
            var binary = '';
            for (var i = 0; i < bytes.byteLength; i++) {
                binary += String.fromCharCode(bytes[i]);
            }
            return btoa(binary).replace(/\+/g, '-').replace(/\//g, '_').replace(/=/g, '');
        }

        function base64UrlToBuffer(base64url) {
            var padding = '='.repeat((4 - base64url.length % 4) % 4);
            var base64 = (base64url + padding).replace(/\-/g, '+').replace(/_/g, '/');
            var rawData = atob(base64);
            var buffer = new Uint8Array(rawData.length);
            for (var i = 0; i < rawData.length; ++i) {
                buffer[i] = rawData.charCodeAt(i);
            }
            return buffer.buffer;
        }

        function setPasskeyLoading(isLoading) {
            var btn = document.getElementById('btn-login-passkey');
            var text = document.getElementById('passkey-btn-text');
            var icon = document.getElementById('passkey-icon-svg');
            var spinner = document.getElementById('passkey-spinner-svg');

            if (btn) btn.disabled = isLoading;
            if (text) text.textContent = isLoading ? 'Memverifikasi...' : 'Login with Passkey';
            if (icon) icon.style.display = isLoading ? 'none' : 'block';
            if (spinner) spinner.style.display = isLoading ? 'block' : 'none';
        }

        window.startPasskeyLogin = async function() {
            if (!window.PublicKeyCredential) {
                alert('{{ __("authentication::messages.passkey_not_supported") }}');
                return;
            }

            try {
                setPasskeyLoading(true);

                // 1. Ambil request options dari server
                var identifierInput = document.querySelector('input[name="identifier"]') || document.querySelector('input[name="email"]');
                var identifier = identifierInput ? identifierInput.value.trim() : '';

                var url = '{{ $optionsRoute }}' + (identifier ? '?identifier=' + encodeURIComponent(identifier) : '');
                var optRes = await fetch(url, {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
                });

                if (!optRes.ok) {
                    throw new Error('Gagal mengambil opsi autentikasi Passkey.');
                }

                var options = await optRes.json();

                // 2. Format challenge dan allowed credentials
                options.challenge = base64UrlToBuffer(options.challenge);
                if (options.allowCredentials && Array.isArray(options.allowCredentials) && options.allowCredentials.length > 0) {
                    options.allowCredentials = options.allowCredentials.map(function(c) {
                        return {
                            id: base64UrlToBuffer(c.id),
                            type: c.type || 'public-key',
                            transports: c.transports
                        };
                    });
                } else {
                    delete options.allowCredentials;
                }

                // 3. Panggil WebAuthn API browser (TouchID, FaceID, Windows Hello, YubiKey)
                var credential;
                try {
                    credential = await navigator.credentials.get({ publicKey: options });
                } catch (navErr) {
                    // Penanganan skenario: User membatalkan dialog biometric atau menutup popup
                    if (navErr.name === 'NotAllowedError' || navErr.name === 'AbortError') {
                        // User membatalkan prompt passkey — kembalikan state tombol tanpa menampilkan alert
                        return;
                    }
                    if (navErr.name === 'InvalidStateError' || navErr.name === 'NotFoundError') {
                        alert('{{ __("authentication::messages.passkey_none_registered") }}');
                        return;
                    }
                    throw navErr;
                }

                if (!credential) {
                    return;
                }

                // 4. Kirim assertion ke server
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
                if (err.name !== 'NotAllowedError' && err.name !== 'AbortError') {
                    console.error('Passkey authentication error:', err);
                    alert(err.message || '{{ __("authentication::messages.passkey_failed") }}');
                }
            } finally {
                setPasskeyLoading(false);
            }
        };

        // Autofill / Conditional UI jika didukung oleh browser
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
