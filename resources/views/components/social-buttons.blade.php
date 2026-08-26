{{-- 
=============================================================================
KOMPONEN: TOMBOL SOCIAL AUTH (GOOGLE & GITHUB SEBELAHAN)
Package: mixudev/laravel-authentication
Deskripsi: Tombol login pihak ketiga yang diletakkan bersebelahan (1 baris) untuk hemat ruang.
=============================================================================
--}}
@php
    $socialConfig = config('authentication.features.social', []);
    $isSocialEnabled = $socialConfig['enabled'] ?? false;
    $providers = $socialConfig['providers'] ?? [];
    
    $googleEnabled = $isSocialEnabled && ($providers['google']['enabled'] ?? false);
    $githubEnabled = $isSocialEnabled && ($providers['github']['enabled'] ?? false);

    $googleUrl = Route::has('social.redirect') 
        ? route('social.redirect', ['provider' => 'google']) 
        : (Route::has('authentication.social.redirect') ? route('authentication.social.redirect', ['provider' => 'google']) : url('/auth/google/redirect'));

    $githubUrl = Route::has('social.redirect') 
        ? route('social.redirect', ['provider' => 'github']) 
        : (Route::has('authentication.social.redirect') ? route('authentication.social.redirect', ['provider' => 'github']) : url('/auth/github/redirect'));
@endphp

@if ($googleEnabled || $githubEnabled)
    <div class="space-y-2">
        <div class="grid grid-cols-{{ ($googleEnabled && $githubEnabled) ? '2' : '1' }} gap-2.5">
            
            {{-- Tombol Google --}}
            @if ($googleEnabled)
                <a 
                    href="{{ $googleUrl }}" 
                    class="auth-btn-secondary flex items-center justify-center gap-2 px-3 py-2 rounded-lg border text-xs font-medium transition shadow-xs"
                >
                    <svg class="w-4 h-4 flex-shrink-0" viewBox="0 0 24 24">
                        <path fill="#EA4335" d="M12 5c1.6 0 3 .6 4.1 1.7l3.1-3.1C17.3 1.8 14.8 1 12 1 7.5 1 3.7 3.6 1.9 7.3l3.7 2.9C6.5 7.3 9 5 12 5z"/>
                        <path fill="#4285F4" d="M23.5 12.3c0-.8-.1-1.6-.2-2.3H12v4.5h6.5c-.3 1.5-1.1 2.8-2.4 3.7l3.7 2.9c2.2-2 3.7-5 3.7-8.8z"/>
                        <path fill="#FBBC05" d="M5.6 14.8c-.2-.7-.4-1.5-.4-2.8s.1-2.1.4-2.8L1.9 6.3C.7 8.7 0 10.8 0 12s.7 3.3 1.9 5.7l3.7-2.9z"/>
                        <path fill="#34A853" d="M12 23c3.2 0 6-1.1 8-3l-3.7-2.9c-1.1.7-2.5 1.2-4.3 1.2-3 0-5.5-2.3-6.4-5.2L1.9 16C3.7 19.7 7.5 23 12 23z"/>
                    </svg>
                    <span>Google</span>
                </a>
            @endif

            {{-- Tombol GitHub --}}
            @if ($githubEnabled)
                <a 
                    href="{{ $githubUrl }}" 
                    class="auth-btn-secondary flex items-center justify-center gap-2 px-3 py-2 rounded-lg border text-xs font-medium transition shadow-xs"
                >
                    <svg class="w-4 h-4 fill-current flex-shrink-0" viewBox="0 0 24 24">
                        <path fill-rule="evenodd" clip-rule="evenodd" d="M12 2C6.477 2 2 6.484 2 12.017c0 4.425 2.865 8.18 6.839 9.504.5.092.682-.217.682-.483 0-.237-.008-.868-.013-1.703-2.782.605-3.369-1.343-3.369-1.343-.454-1.158-1.11-1.466-1.11-1.466-.908-.62.069-.608.069-.608 1.003.07 1.53 1.032 1.53 1.032.892 1.53 2.341 1.088 2.91.832.092-.647.35-1.088.636-1.338-2.22-.253-4.555-1.113-4.555-4.951 0-1.093.39-1.988 1.029-2.688-.103-.253-.446-1.272.098-2.65 0 0 .84-.27 2.75 1.026A9.564 9.564 0 0112 6.844c.85.004 1.705.115 2.504.337 1.909-1.296 2.747-1.027 2.747-1.027.546 1.379.202 2.398.1 2.651.64.7 1.028 1.595 1.028 2.688 0 3.848-2.339 4.695-4.566 4.943.359.309.678.92.678 1.855 0 1.338-.012 2.419-.012 2.747 0 .268.18.58.688.482A10.019 10.019 0 0022 12.017C22 6.484 17.522 2 12 2z"/>
                    </svg>
                    <span>GitHub</span>
                </a>
            @endif

        </div>
    </div>
@endif
