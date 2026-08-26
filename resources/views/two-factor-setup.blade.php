{{-- 
=============================================================================
HALAMAN VIEW: PENGATURAN 2FA (TOTP SETUP)
Package: mixudev/laravel-authentication
Deskripsi: Halaman setup TOTP, QR Code scan, secret key, dan recovery codes.
=============================================================================
--}}
@php
    $activeLayout = config('authentication.ui.layout', 'card') === 'split' 
        ? 'authentication::layouts.split' 
        : 'authentication::layouts.card';

    $confirmRoute = Route::has('two-factor.enable') 
        ? route('two-factor.enable') 
        : url('/auth/two-factor/confirm');
@endphp

<x-dynamic-component :component="$activeLayout" :title="__('authentication::messages.two_factor_title')">
    
    <div class="space-y-4">
        
        {{-- Header Halaman --}}
        <x-authentication::header 
            :title="__('authentication::messages.two_factor_title')"
            subtitle="Pindai QR code atau masukkan kode rahasia ke aplikasi Authenticator Anda (Google Authenticator, Authy, atau 1Password)."
        />

        {{-- Alert Notifikasi --}}
        @if ($errors->any())
            <x-authentication::alert type="error" :autodismiss="true" :message="$errors->first()" />
        @endif

        {{-- Secret Key & Manual Entry --}}
        <div class="p-3 bg-slate-50 border border-slate-200 rounded-xl space-y-2 text-center">
            <div class="text-[11px] font-semibold text-slate-500 uppercase tracking-wider">Kunci Rahasia Manual</div>
            <div class="font-mono text-sm font-bold text-slate-800 tracking-widest select-all bg-white py-1 px-2 rounded border border-slate-200 inline-block">
                {{ $secret }}
            </div>
            <p class="text-[11px] text-slate-400">
                URI: <span class="font-mono text-[10px] truncate inline-block max-w-full text-slate-600 select-all">{{ $otpauthUrl }}</span>
            </p>
        </div>

        {{-- Recovery Backup Codes --}}
        @if (!empty($recoveryCodes))
            <div class="p-3 bg-amber-50/70 border border-amber-200 rounded-xl space-y-2">
                <div class="text-xs font-semibold text-amber-900 flex items-center space-x-1">
                    <span>⚠️ Simpan Kode Cadangan Pemulihan</span>
                </div>
                <p class="text-[11px] text-amber-700">
                    Gunakan kode ini jika Anda kehilangan akses ke aplikasi autentikator. Setiap kode hanya bisa digunakan 1 kali.
                </p>
                <div class="grid grid-cols-2 gap-1.5 font-mono text-xs text-slate-700 bg-white p-2 rounded border border-amber-100 select-all">
                    @foreach ($recoveryCodes as $code)
                        <div class="text-center">{{ $code }}</div>
                    @endforeach
                </div>
            </div>
        @endif

        {{-- Form Konfirmasi TOTP --}}
        <form method="POST" action="{{ $confirmRoute }}" class="space-y-4">
            @csrf

            <x-authentication::input 
                name="code" 
                type="text" 
                inputmode="numeric"
                maxlength="6"
                label="Konfirmasi dengan Kode 6-Digit dari Aplikasi"
                placeholder="Contoh: 123456" 
                autofocus
                icon="shield"
            />

            <x-authentication::button type="submit" variant="primary" block="true">
                Aktifkan 2FA Sekarang
            </x-authentication::button>
        </form>

        {{-- Link Batal --}}
        <div class="text-center border-t border-slate-100 pt-3">
            <a href="{{ config('authentication.redirects.login', '/dashboard') }}" class="text-xs text-slate-500 hover:text-slate-700 transition">
                ← Batal & Kembali
            </a>
        </div>

    </div>

</x-dynamic-component>
