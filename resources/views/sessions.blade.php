{{-- 
=============================================================================
HALAMAN VIEW: MANAJEMEN SESI & PERANGKAT
Package: mixudev/laravel-authentication
Deskripsi: Halaman daftar sesi aktif, info device/browser/IP, dan tombol revoke.
=============================================================================
--}}
@php
    $activeLayout = config('authentication.ui.layout', 'card') === 'split' 
        ? 'authentication::layouts.split' 
        : 'authentication::layouts.card';

    $revokeOthersRoute = Route::has('auth.sessions.destroy-others') 
        ? route('auth.sessions.destroy-others') 
        : url('/auth/sessions/revoke-others');
@endphp

<x-dynamic-component :component="$activeLayout" :title="__('authentication::messages.sessions_title')">
    
    <div class="space-y-5">
        
        {{-- Header Halaman --}}
        <x-authentication::header 
            :title="__('authentication::messages.sessions_title')"
            :subtitle="__('authentication::messages.sessions_subtitle')"
        />

        {{-- Alert Notifikasi --}}
        @if (session('status'))
            <x-authentication::alert type="success" :autodismiss="true" :message="session('status')" />
        @endif

        @if ($errors->any())
            <x-authentication::alert type="error" :autodismiss="true" :message="$errors->first()" />
        @endif

        {{-- List Sesi / Perangkat --}}
        <div class="space-y-3">
            @forelse ($sessions as $session)
                <div class="p-3.5 rounded-xl border {{ $session['is_current_device'] ? 'border-blue-200 bg-blue-50/40' : 'border-slate-200 bg-white' }} flex items-center justify-between transition">
                    <div class="space-y-1">
                        <div class="flex items-center space-x-2">
                            <span class="text-sm font-semibold text-slate-800">{{ $session['device_name'] }}</span>
                            @if ($session['is_current_device'])
                                <span class="px-2 py-0.5 text-[10px] font-bold rounded-full bg-blue-100 text-blue-700">
                                    {{ __('authentication::messages.current_session') }}
                                </span>
                            @endif
                        </div>
                        <div class="text-xs text-slate-500 flex items-center space-x-2">
                            <span>IP: {{ $session['ip_address'] }}</span>
                            @if ($session['location'])
                                <span>• {{ $session['location'] }}</span>
                            @endif
                        </div>
                        <div class="text-[11px] text-slate-400">
                            {{ __('authentication::messages.last_active') }}: {{ \Carbon\Carbon::parse($session['last_activity'])->diffForHumans() }}
                        </div>
                    </div>

                    @if (!$session['is_current_device'])
                        <form method="POST" action="{{ route('auth.sessions.destroy', $session['id']) }}">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="text-xs font-semibold text-rose-600 hover:text-rose-700 hover:underline px-2 py-1">
                                {{ __('authentication::messages.revoke_btn') }}
                            </button>
                        </form>
                    @endif
                </div>
            @empty
                <div class="text-center py-4 text-xs text-slate-500">
                    Tidak ada sesi aktif lain yang ditemukan.
                </div>
            @endforelse
        </div>

        {{-- Form Revoke Other Sessions --}}
        @if (count($sessions) > 1)
            <div class="pt-3 border-t border-slate-100" x-data="{ openConfirm: false }">
                <button 
                    type="button" 
                    @click="openConfirm = !openConfirm"
                    class="w-full text-center text-xs font-semibold text-rose-600 hover:text-rose-700 transition"
                >
                    {{ __('authentication::messages.revoke_others_btn') }}
                </button>

                <div x-show="openConfirm" class="mt-3 p-3 rounded-lg bg-rose-50/50 border border-rose-100 space-y-3" style="display: none;">
                    <p class="text-xs text-rose-800">
                        Masukkan kata sandi Anda untuk memastikan pengeluaran dari seluruh perangkat lain.
                    </p>
                    <form method="POST" action="{{ $revokeOthersRoute }}" class="space-y-2">
                        @csrf
                        <input 
                            name="password" 
                            type="password" 
                            placeholder="{{ __('authentication::messages.password_placeholder') }}" 
                            class="w-full px-3 py-2 text-xs rounded-lg border border-slate-300 focus:outline-none focus:ring-2 focus:ring-rose-500"
                            required
                        >
                        <x-authentication::button type="submit" variant="primary" block="true" class="!bg-rose-600 hover:!bg-rose-700">
                            Konfirmasi Cabut Semua Sesi Lain
                        </x-authentication::button>
                    </form>
                </div>
            </div>
        @endif

        {{-- Link Kembali ke Dashboard --}}
        <div class="text-center border-t border-slate-100 pt-3">
            <a href="{{ config('authentication.redirects.login', '/dashboard') }}" class="text-xs text-slate-500 hover:text-slate-700 transition">
                ← Kembali ke Dashboard
            </a>
        </div>

    </div>

</x-dynamic-component>
