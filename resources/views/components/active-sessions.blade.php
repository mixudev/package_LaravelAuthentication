{{-- 
=============================================================================
KOMPONEN: ACTIVE SESSIONS & DEVICE MANAGEMENT (REUSABLE COMPONENT)
Package: mixudev/laravel-authentication
Penggunaan: <x-authentication::active-sessions />
Deskripsi: Komponen mandiri untuk disisipkan ke halaman Dashboard/Profile project.
=============================================================================
--}}
@props([
    'user' => null,
    'title' => null,
    'subtitle' => null,
])

@php
    use Vendor\LaravelAuthentication\Services\Session\SessionManagerService;

    $currentUser = $user ?? auth()->user();
    $sessions = [];

    if ($currentUser) {
        /** @var SessionManagerService $sessionService */
        $sessionService = app(SessionManagerService::class);
        $currentSessionId = request()->hasSession() ? request()->session()->getId() : null;
        $sessions = $sessionService->getActiveSessions($currentUser, $currentSessionId);
    }

    $cardTitle = $title ?? __('authentication::messages.sessions_title', [], null) ?? 'Sesi & Perangkat Aktif';
    $cardSubtitle = $subtitle ?? __('authentication::messages.sessions_subtitle', [], null) ?? 'Kelola dan cabut akses login Anda di perangkat lain.';
    $revokeOthersRoute = Route::has('auth.sessions.destroy-others') 
        ? route('auth.sessions.destroy-others') 
        : url('/auth/sessions/revoke-others');
@endphp

<div class="auth-card bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-xl p-5 sm:p-6 shadow-xs space-y-4">
    
    {{-- Header Komponen --}}
    <div class="flex items-center justify-between border-b border-zinc-100 dark:border-zinc-800 pb-3">
        <div class="space-y-0.5">
            <h3 class="text-sm font-bold text-zinc-900 dark:text-zinc-100 flex items-center gap-2">
                <svg class="w-4 h-4 text-zinc-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                </svg>
                <span>{{ $cardTitle }}</span>
            </h3>
            <p class="text-xs text-zinc-500 dark:text-zinc-400">{{ $cardSubtitle }}</p>
        </div>
        <span class="text-xs font-semibold px-2.5 py-0.5 rounded-full bg-zinc-100 dark:bg-zinc-800 text-zinc-700 dark:text-zinc-300">
            {{ count($sessions) }} {{ count($sessions) === 1 ? 'Perangkat' : 'Perangkat' }}
        </span>
    </div>

    {{-- Alert Pesan Sukses / Error Sesi --}}
    @if (session('status'))
        <x-authentication::alert type="success" :autodismiss="true" :message="session('status')" />
    @endif
    @if ($errors->has('password'))
        <x-authentication::alert type="error" :autodismiss="true" :message="$errors->first('password')" />
    @endif

    {{-- List Perangkat Aktif --}}
    <div class="space-y-2.5">
        @forelse ($sessions as $session)
            <div class="p-3.5 rounded-lg border {{ $session['is_current_device'] ? 'border-zinc-900/20 dark:border-zinc-100/20 bg-zinc-50/60 dark:bg-zinc-800/40' : 'border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900/50' }} flex items-center justify-between transition">
                <div class="flex items-center gap-3">
                    {{-- Device Icon --}}
                    <div class="w-8 h-8 rounded-lg bg-zinc-100 dark:bg-zinc-800 flex items-center justify-center text-zinc-600 dark:text-zinc-300">
                        @if (str_contains(strtolower($session['platform']), 'ios') || str_contains(strtolower($session['platform']), 'android'))
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                            </svg>
                        @else
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                            </svg>
                        @endif
                    </div>

                    <div class="space-y-0.5">
                        <div class="flex items-center gap-2">
                            <span class="text-xs font-bold text-zinc-900 dark:text-zinc-100">{{ $session['device_name'] }}</span>
                            @if ($session['is_current_device'])
                                <span class="px-2 py-0.5 text-[10px] font-bold rounded-full bg-emerald-100 dark:bg-emerald-950 text-emerald-700 dark:text-emerald-300">
                                    {{ __('authentication::messages.current_session') }}
                                </span>
                            @endif
                        </div>
                        <div class="text-[11px] text-zinc-500 dark:text-zinc-400 flex items-center gap-2">
                            <span>{{ $session['ip_address'] }}</span>
                            @if ($session['location'])
                                <span>• {{ $session['location'] }}</span>
                            @endif
                            <span>• {{ \Carbon\Carbon::parse($session['last_activity'])->diffForHumans() }}</span>
                        </div>
                    </div>
                </div>

                @if (!$session['is_current_device'])
                    <form method="POST" action="{{ route('auth.sessions.destroy', $session['id']) }}" onsubmit="return confirm('Cabut akses login untuk perangkat ini?');">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="text-xs font-semibold text-rose-600 dark:text-rose-400 hover:text-rose-700 dark:hover:text-rose-300 hover:underline px-2 py-1 cursor-pointer">
                            {{ __('authentication::messages.revoke_btn') }}
                        </button>
                    </form>
                @endif
            </div>
        @empty
            <div class="text-center py-4 text-xs text-zinc-500 dark:text-zinc-400">
                Tidak ada sesi aktif lain yang ditemukan.
            </div>
        @endforelse
    </div>

    {{-- Tombol Cabut Semua Sesi Lain --}}
    @if (count($sessions) > 1)
        <div class="pt-2 border-t border-zinc-100 dark:border-zinc-800" x-data="{ openConfirm: false }">
            <button 
                type="button" 
                @click="openConfirm = !openConfirm"
                class="w-full text-center text-xs font-bold text-rose-600 dark:text-rose-400 hover:text-rose-700 dark:hover:text-rose-300 transition py-1 cursor-pointer"
            >
                {{ __('authentication::messages.revoke_others_btn') }}
            </button>

            <div x-show="openConfirm" class="mt-3 p-3.5 rounded-lg bg-rose-50/70 dark:bg-rose-950/30 border border-rose-200 dark:border-rose-900/50 space-y-2.5" style="display: none;">
                <p class="text-xs text-rose-900 dark:text-rose-200 font-medium">
                    Masukkan kata sandi Anda untuk mengonfirmasi pengeluaran dari seluruh perangkat lain.
                </p>
                <form method="POST" action="{{ $revokeOthersRoute }}" class="space-y-2">
                    @csrf
                    <input 
                        name="password" 
                        type="password" 
                        placeholder="{{ __('authentication::messages.password_placeholder') }}" 
                        class="w-full px-3 py-2 text-xs rounded-lg border border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-900 text-zinc-900 dark:text-zinc-100 focus:outline-none focus:ring-1 focus:ring-rose-500"
                        required
                    >
                    <x-authentication::button type="submit" variant="primary" block="true" class="!bg-rose-600 hover:!bg-rose-700 !text-white text-xs">
                        Konfirmasi Cabut Semua Sesi Lain
                    </x-authentication::button>
                </form>
            </div>
        </div>
    @endif

</div>
