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

    $cancelRoute = Route::has('auth.sessions.index')
        ? route('auth.sessions.index')
        : config('authentication.redirects.login', '/dashboard');
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
        <div class="p-4 bg-zinc-50 dark:bg-zinc-900/60 border border-zinc-200 dark:border-zinc-800 rounded-2xl flex flex-col items-center justify-center space-y-3 text-center">
            @if (!empty($qrCodeUrl))
                <div class="p-3 bg-white rounded-xl shadow-xs border border-zinc-200 dark:border-zinc-700 inline-block">
                    <img 
                        src="{{ $qrCodeUrl }}" 
                        alt="QR Code Autentikasi 2 Langkah" 
                        class="w-48 h-48 block mx-auto rounded-lg"
                        loading="eager"
                    />
                </div>
                <p class="text-xs text-zinc-500 dark:text-zinc-400 font-medium">
                    Buka Google Authenticator &gt; Tap <strong class="text-zinc-800 dark:text-zinc-200">(+)</strong> &gt; Pilih <strong class="text-zinc-800 dark:text-zinc-200">Scan a QR code</strong>
                </p>
            @endif

            {{-- Toggle Input Manual --}}
            <button 
                type="button" 
                @click="manualEntry = !manualEntry"
                class="text-xs text-indigo-600 dark:text-indigo-400 hover:text-indigo-700 dark:hover:text-indigo-300 font-semibold hover:underline transition pt-1 cursor-pointer"
                x-text="manualEntry ? 'Tutup Kunci Manual' : 'Tidak bisa scan? Gunakan Kunci Manual'"
            ></button>

            {{-- Secret Key & Manual Entry --}}
            <div x-show="manualEntry" class="w-full pt-2 border-t border-zinc-200/80 dark:border-zinc-800 space-y-2 text-center" style="display: none;">
                <div class="text-[11px] font-semibold text-zinc-500 dark:text-zinc-400 uppercase tracking-wider">Kunci Rahasia Manual</div>
                <div class="font-mono text-sm font-bold text-zinc-900 dark:text-zinc-100 tracking-widest select-all bg-white dark:bg-zinc-800 py-1.5 px-3 rounded-lg border border-zinc-200 dark:border-zinc-700 inline-block shadow-inner">
                    {{ $secret }}
                </div>
                <p class="text-[11px] text-zinc-400 dark:text-zinc-500">
                    Akun: <span class="font-mono text-zinc-600 dark:text-zinc-300">{{ auth()->user()->email ?? auth()->user()->username ?? 'Akun Saya' }}</span>
                </p>
            </div>
        </div>

        {{-- Recovery Backup Codes --}}
        @if (!empty($recoveryCodes))
            <div class="p-3.5 bg-amber-50/80 dark:bg-amber-950/30 border border-amber-200 dark:border-amber-900/60 rounded-xl space-y-2">
                <div class="text-xs font-bold text-amber-900 dark:text-amber-300 flex items-center space-x-1">
                    <span>⚠️ Simpan Kode Cadangan Pemulihan</span>
                </div>
                <p class="text-[11px] text-amber-800 dark:text-amber-400 leading-relaxed">
                    Simpan kode-kode ini di tempat aman. Gunakan jika Anda kehilangan akses ke ponsel/aplikasi autentikator. Setiap kode hanya berlaku 1 kali.
                </p>
                <div class="grid grid-cols-2 gap-1.5 font-mono text-xs text-zinc-800 dark:text-zinc-200 bg-white dark:bg-zinc-900 p-2.5 rounded-lg border border-amber-200/70 dark:border-amber-900/50 select-all shadow-inner">
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
        <div class="text-center border-t border-zinc-200 dark:border-zinc-800 pt-3">
            <a href="{{ $cancelRoute }}" class="text-xs text-zinc-500 dark:text-zinc-400 hover:text-zinc-700 dark:hover:text-zinc-200 transition">
                ← Batal & Kembali
            </a>
        </div>

    </div>

</x-dynamic-component>
