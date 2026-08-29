{{-- 
=============================================================================
HALAMAN VIEW: PUSAT KEAMANAN & MANAJEMEN AKUN (SECURITY DASHBOARD)
Package: mixudev/laravel-authentication
Deskripsi: Dashboard lengkap manajemen 2FA, sesi perangkat, profil akun, dan riwayat login.
=============================================================================
--}}
@php
    $brandName = config('authentication.ui.brand_name') ?: config('app.name', 'Laravel');
    $userInitial = strtoupper(substr($user->name ?? $user->email ?? 'U', 0, 1));
    $userEmail = $user->email ?? $user->username ?? 'user@example.com';
    $userName = $user->name ?? 'Pengguna';
    $isEmailVerified = method_exists($user, 'hasVerifiedEmail') ? $user->hasVerifiedEmail() : true;

    $twoFactorSetupRoute = Route::has('two-factor.setup') 
        ? route('two-factor.setup') 
        : url('/auth/two-factor/setup');

    $twoFactorDisableRoute = Route::has('two-factor.disable') 
        ? route('two-factor.disable') 
        : url('/auth/two-factor/disable');

    $logoutRoute = Route::has('logout') 
        ? route('logout') 
        : url('/logout');

    $confirmPasswordRoute = Route::has('password.confirm') 
        ? route('password.confirm') 
        : url('/confirm-password');
@endphp

<x-authentication::layouts.auth :title="'Pusat Keamanan Akun — ' . $brandName">
    <div class="min-h-screen py-8 sm:py-12 px-4 sm:px-6 lg:px-8 max-w-5xl mx-auto space-y-6">
        
        {{-- Navbar / Top Header --}}
        <header class="flex flex-col sm:flex-row sm:items-center sm:justify-between pb-6 border-b border-zinc-200 dark:border-zinc-800 gap-4">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-zinc-900 dark:bg-zinc-100 flex items-center justify-center text-white dark:text-zinc-950 font-bold shadow-xs text-sm">
                    {{ $userInitial }}
                </div>
                <div>
                    <h1 class="text-lg font-bold text-zinc-900 dark:text-zinc-100 flex items-center gap-2">
                        <span>{{ $userName }}</span>
                        @if ($isEmailVerified)
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-semibold bg-emerald-100 dark:bg-emerald-950/80 text-emerald-700 dark:text-emerald-300">
                                Terverifikasi
                            </span>
                        @endif
                    </h1>
                    <p class="text-xs text-zinc-500 dark:text-zinc-400">{{ $userEmail }}</p>
                </div>
            </div>

            <div class="flex items-center gap-2">
                <form method="POST" action="{{ $logoutRoute }}" class="inline">
                    @csrf
                    <button type="submit" class="px-3.5 py-1.5 text-xs font-semibold rounded-lg border border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-800 text-zinc-700 dark:text-zinc-200 hover:bg-zinc-50 dark:hover:bg-zinc-700 transition cursor-pointer shadow-2xs">
                        Keluar (Logout)
                    </button>
                </form>
            </div>
        </header>

        {{-- Alert Notifikasi Status --}}
        @if (session('status'))
            <x-authentication::alert type="success" :autodismiss="true" :message="session('status')" />
        @endif

        @if ($errors->any())
            <x-authentication::alert type="error" :autodismiss="true" :message="$errors->first()" />
        @endif

        {{-- Grid 2 Kolom: Modul Keamanan Utama --}}
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

            {{-- Kolom Kiri: Profil & Autentikasi 2 Langkah (2FA) --}}
            <div class="lg:col-span-1 space-y-6">
                
                {{-- Modul 1: Autentikasi 2 Langkah (2FA TOTP) --}}
                <div class="auth-card bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-xl p-5 shadow-xs space-y-4" x-data="{ showDisableModal: false }">
                    <div class="flex items-center justify-between border-b border-zinc-100 dark:border-zinc-800 pb-3">
                        <div class="flex items-center gap-2">
                            <svg class="w-4 h-4 text-zinc-700 dark:text-zinc-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                            </svg>
                            <h2 class="text-sm font-bold text-zinc-900 dark:text-zinc-100">Autentikasi 2 Langkah</h2>
                        </div>
                        
                        @if ($isTwoFactorEnabled)
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-100 dark:bg-emerald-950 text-emerald-700 dark:text-emerald-300">
                                Aktif
                            </span>
                        @else
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-100 dark:bg-amber-950 text-amber-800 dark:text-amber-300">
                                Belum Aktif
                            </span>
                        @endif
                    </div>

                    <p class="text-xs text-zinc-600 dark:text-zinc-400 leading-relaxed">
                        @if ($isTwoFactorEnabled)
                            Akun Anda terlindungi dengan kode keamanan 6-digit dari aplikasi autentikator (Google Authenticator / Aegis).
                        @else
                            Tambahkan lapisan keamanan ekstra pada akun Anda menggunakan aplikasi autentikator di ponsel.
                        @endif
                    </p>

                    @if ($isTwoFactorEnabled)
                        <button 
                            type="button" 
                            @click="showDisableModal = true"
                            class="w-full text-center text-xs font-semibold py-2 px-3 rounded-lg border border-rose-200 dark:border-rose-900 bg-rose-50/50 dark:bg-rose-950/20 text-rose-600 dark:text-rose-400 hover:bg-rose-100 dark:hover:bg-rose-900/30 transition cursor-pointer"
                        >
                            Matikan Autentikasi 2 Langkah (2FA)
                        </button>

                        {{-- Modal Konfirmasi Matikan 2FA --}}
                        <div x-show="showDisableModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-xs" style="display: none;">
                            <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-xl max-w-sm w-full p-5 space-y-4 shadow-xl" @click.away="showDisableModal = false">
                                <h3 class="text-sm font-bold text-zinc-900 dark:text-zinc-100">Konfirmasi Matikan 2FA</h3>
                                <p class="text-xs text-zinc-600 dark:text-zinc-400">
                                    Masukkan kata sandi akun Anda untuk mengonfirmasi penonaktifan autentikasi 2 langkah.
                                </p>
                                <form method="POST" action="{{ $twoFactorDisableRoute }}" class="space-y-3">
                                    @csrf
                                    @method('DELETE')
                                    <input 
                                        type="password" 
                                        name="password" 
                                        placeholder="Kata Sandi Saat Ini" 
                                        required 
                                        class="w-full px-3 py-2 text-xs rounded-lg border border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-800 text-zinc-900 dark:text-zinc-100 focus:outline-none focus:ring-1 focus:ring-zinc-900 dark:focus:ring-zinc-400"
                                    >
                                    <div class="flex items-center justify-end gap-2 pt-1">
                                        <button type="button" @click="showDisableModal = false" class="px-3 py-1.5 text-xs text-zinc-600 dark:text-zinc-400 hover:underline cursor-pointer">
                                            Batal
                                        </button>
                                        <x-authentication::button type="submit" variant="primary" class="!bg-rose-600 hover:!bg-rose-700 text-xs">
                                            Ya, Matikan 2FA
                                        </x-authentication::button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    @else
                        <a 
                            href="{{ $twoFactorSetupRoute }}"
                            class="block w-full text-center text-xs font-semibold py-2 px-3 rounded-lg bg-zinc-900 dark:bg-zinc-100 text-white dark:text-zinc-950 hover:bg-zinc-800 dark:hover:bg-white transition shadow-xs"
                        >
                            Aktifkan 2FA Sekarang
                        </a>
                    @endif
                </div>

                {{-- Modul 2: Ringkasan Metrik Keamanan --}}
                <div class="auth-card bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-xl p-5 shadow-xs space-y-3">
                    <h2 class="text-xs font-bold text-zinc-500 dark:text-zinc-400 uppercase tracking-wider">Kesehatan Keamanan Akun</h2>
                    <ul class="space-y-2 text-xs">
                        <li class="flex items-center justify-between py-1 border-b border-zinc-100 dark:border-zinc-800/60">
                            <span class="text-zinc-600 dark:text-zinc-400">Total Sesi Aktif</span>
                            <span class="font-bold text-zinc-900 dark:text-zinc-100">{{ $summary['total_sessions'] ?? count($sessions) }}</span>
                        </li>
                        <li class="flex items-center justify-between py-1 border-b border-zinc-100 dark:border-zinc-800/60">
                            <span class="text-zinc-600 dark:text-zinc-400">Status 2FA TOTP</span>
                            <span class="font-bold {{ $isTwoFactorEnabled ? 'text-emerald-600 dark:text-emerald-400' : 'text-amber-600 dark:text-amber-400' }}">
                                {{ $isTwoFactorEnabled ? 'Aktif' : 'Nonaktif' }}
                            </span>
                        </li>
                        <li class="flex items-center justify-between py-1">
                            <span class="text-zinc-600 dark:text-zinc-400">Perangkat Lain</span>
                            <span class="font-bold text-zinc-900 dark:text-zinc-100">{{ $summary['other_sessions_count'] ?? max(0, count($sessions) - 1) }}</span>
                        </li>
                    </ul>
                </div>

            </div>

            {{-- Kolom Kanan: Manajemen Sesi Perangkat & Riwayat Login --}}
            <div class="lg:col-span-2 space-y-6">
                
                {{-- Modul 3: Manajemen Sesi & Perangkat Aktif --}}
                <x-authentication::active-sessions :user="$user" />

                {{-- Modul 4: Riwayat Aktivitas Login Terakhir --}}
                @if (!empty($recentLogins))
                    <div class="auth-card bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-xl p-5 sm:p-6 shadow-xs space-y-3">
                        <div class="flex items-center justify-between border-b border-zinc-100 dark:border-zinc-800 pb-3">
                            <h3 class="text-sm font-bold text-zinc-900 dark:text-zinc-100 flex items-center gap-2">
                                <svg class="w-4 h-4 text-zinc-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                                <span>Riwayat Login Terakhir</span>
                            </h3>
                            <span class="text-[11px] text-zinc-400">5 aktivitas terakhir</span>
                        </div>

                        <div class="overflow-x-auto">
                            <table class="w-full text-left text-xs">
                                <thead>
                                    <tr class="text-zinc-400 dark:text-zinc-500 border-b border-zinc-100 dark:border-zinc-800">
                                        <th class="py-2 font-medium">Metode</th>
                                        <th class="py-2 font-medium">IP Address</th>
                                        <th class="py-2 font-medium">Waktu Masuk</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800/60 text-zinc-700 dark:text-zinc-300">
                                    @foreach ($recentLogins as $login)
                                        <tr>
                                            <td class="py-2.5 font-semibold text-zinc-900 dark:text-zinc-100">
                                                {{ ucfirst($login['login_method'] ?? 'Standard') }}
                                            </td>
                                            <td class="py-2.5 font-mono text-[11px] text-zinc-500 dark:text-zinc-400">
                                                {{ $login['ip_address'] ?? '127.0.0.1' }}
                                            </td>
                                            <td class="py-2.5 text-zinc-500 dark:text-zinc-400">
                                                {{ \Carbon\Carbon::parse($login['login_at'])->diffForHumans() }}
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                @endif

            </div>

        </div>

    </div>
</x-authentication::layouts.auth>

