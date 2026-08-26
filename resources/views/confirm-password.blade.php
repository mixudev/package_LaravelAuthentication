{{-- 
=============================================================================
HALAMAN VIEW: CONFIRM PASSWORD
Package: mixudev/laravel-authentication
Deskripsi: Halaman konfirmasi kata sandi sebelum mengakses area sensitif.
=============================================================================
--}}
@php
    $activeLayout = config('authentication.ui.layout', 'card') === 'split' 
        ? 'authentication::layouts.split' 
        : 'authentication::layouts.card';

    $confirmRoute = Route::has('password.confirm.submit') 
        ? route('password.confirm.submit') 
        : url('/confirm-password');
@endphp

<x-dynamic-component :component="$activeLayout" :title="__('authentication::messages.confirm_password_title')">
    
    <div class="space-y-4">
        
        {{-- Header Halaman --}}
        <x-authentication::header 
            :title="__('authentication::messages.confirm_password_title')"
            :subtitle="__('authentication::messages.confirm_password_subtitle')"
        />

        {{-- Alert Notifikasi --}}
        @if ($errors->any())
            <x-authentication::alert type="error" :autodismiss="true" :message="$errors->first()" />
        @endif

        {{-- Form Konfirmasi Password --}}
        <form method="POST" action="{{ $confirmRoute }}" class="space-y-4">
            @csrf

            <x-authentication::input 
                name="password" 
                type="password" 
                label="{{ __('authentication::messages.password_label') }}"
                placeholder="{{ __('authentication::messages.password_placeholder') }}" 
                autocomplete="current-password"
                autofocus
                icon="lock"
            />

            {{-- Tombol Submit --}}
            <x-authentication::button type="submit" variant="primary" block="true">
                {{ __('authentication::messages.confirm_password_btn') }}
            </x-authentication::button>
        </form>

        {{-- Link Batal / Kembali ke Dashboard --}}
        <div class="text-center border-t border-slate-100 pt-3">
            <a href="{{ url()->previous() }}" class="text-xs text-slate-500 hover:text-slate-700 transition">
                ← Batal & Kembali
            </a>
        </div>

    </div>

</x-dynamic-component>
