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
    
    <div class="space-y-4" x-data="{ manualEntry: false }">
        
        {{-- Header Halaman --}}
        <x-authentication::header 
            :title="__('authentication::messages.two_factor_title')"
            subtitle="Pindai QR Code di bawah menggunakan aplikasi Google Authenticator, Authy, atau 1Password di ponsel Anda."
        />

        {{-- Alert Notifikasi --}}
        @if ($errors->any())
            <x-authentication::alert type="error" :autodismiss="true" :message="$errors->first()" />
        @endif

        {{-- Visual QR Code Container --}}
        <div class="p-4 bg-slate-50 border border-slate-200 rounded-2xl flex flex-col items-center justify-center space-y-3 text-center">
            @if (!empty($qrCodeUrl))
                <div class="p-3 bg-white rounded-xl shadow-sm border border-slate-200 inline-block">
                    <img 
                        src="{{ $qrCodeUrl }}" 
                        alt="QR Code Autentikasi 2 Langkah" 
                        class="w-48 h-48 block mx-auto rounded-lg"
                        loading="eager"
                    />
                </div>
                <p class="text-xs text-slate-500 font-medium">
                    Buka Google Authenticator &gt; Tap <strong class="text-slate-700">(+)</strong> &gt; Pilih <strong class="text-slate-700">Scan a QR code</strong>
                </p>
            @endif

            {{-- Toggle Input Manual --}}
            <button 
                type="button" 
                @click="manualEntry = !manualEntry"
                class="text-xs text-blue-600 hover:text-blue-700 font-semibold hover:underline transition pt-1"
                x-text="manualEntry ? 'Tutup Kunci Manual' : 'Tidak bisa scan? Gunakan Kunci Manual'"
            ></button>

            {{-- Secret Key & Manual Entry --}}
            <div x-show="manualEntry" class="w-full pt-2 border-t border-slate-200/70 space-y-2 text-center" style="display: none;">
                <div class="text-[11px] font-semibold text-slate-500 uppercase tracking-wider">Kunci Rahasia Manual</div>
                <div class="font-mono text-sm font-bold text-slate-800 tracking-widest select-all bg-white py-1.5 px-3 rounded-lg border border-slate-200 inline-block shadow-inner">
                    {{ $secret }}
                </div>
                <p class="text-[11px] text-slate-400">
                    Akun: <span class="font-mono text-slate-600">{{ auth()->user()->email ?? auth()->user()->username ?? 'Akun Saya' }}</span>
                </p>
            </div>
        </div>

        {{-- Recovery Backup Codes --}}
        @if (!empty($recoveryCodes))
            <div class="p-3.5 bg-amber-50/80 border border-amber-200 rounded-xl space-y-2">
                <div class="text-xs font-bold text-amber-900 flex items-center space-x-1">
                    <span>⚠️ Simpan Kode Cadangan Pemulihan</span>
                </div>
                <p class="text-[11px] text-amber-800 leading-relaxed">
                    Simpan kode-kode ini di tempat aman. Gunakan jika Anda kehilangan akses ke ponsel/aplikasi autentikator. Setiap kode hanya berlaku 1 kali.
                </p>
                <div class="grid grid-cols-2 gap-1.5 font-mono text-xs text-slate-800 bg-white p-2.5 rounded-lg border border-amber-200/70 select-all shadow-inner">
                    @foreach ($recoveryCodes as $code)
                        <div class="text-center font-bold tracking-wider">{{ $code }}</div>
                    @endforeach
                </div>
            </div>
        @endif

        {{-- Form Konfirmasi TOTP --}}
        <form method="POST" action="{{ $confirmRoute }}" class="space-y-4 pt-1">
            @csrf

            <x-authentication::input 
                name="code" 
                type="text" 
                inputmode="numeric"
                maxlength="6"
                label="Masukkan Kode 6-Digit dari Aplikasi untuk Konfirmasi"
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
