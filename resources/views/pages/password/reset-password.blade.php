{{--
=============================================================================
HALAMAN VIEW: RESET PASSWORD
Package: mixudev/laravel-authentication
Deskripsi: Halaman reset password dengan alert di atas form dan auto-dismiss 3 detik.
=============================================================================
--}}
@php
    $activeLayout = config('authentication.ui.layout', 'card') === 'split'
        ? 'authentication::layouts.split'
        : 'authentication::layouts.card';

    $updateRoute = Route::has(\Vendor\LaravelAuthentication\Support\RouteConfig::name('password.update'))
        ? route(\Vendor\LaravelAuthentication\Support\RouteConfig::name('password.update'))
        : (Route::has(\Vendor\LaravelAuthentication\Support\RouteConfig::name('password.update')) ? route(\Vendor\LaravelAuthentication\Support\RouteConfig::name('password.update')) : url('/reset-password'));

    $loginRoute = Route::has(\Vendor\LaravelAuthentication\Support\RouteConfig::name('login'))
        ? route(\Vendor\LaravelAuthentication\Support\RouteConfig::name('login'))
        : (Route::has(\Vendor\LaravelAuthentication\Support\RouteConfig::name('login')) ? route(\Vendor\LaravelAuthentication\Support\RouteConfig::name('login')) : url('/login'));
@endphp

<x-dynamic-component :component="$activeLayout" :title="__('authentication::messages.reset_title')">

    <div class="space-y-4">

        <x-authentication::header
            :title="__('authentication::messages.reset_title')"
            :subtitle="__('authentication::messages.reset_subtitle')"
        />

        {{-- Alert di atas form, hilang otomatis dalam 3 detik --}}
        @if (session('status'))
            <x-authentication::feedback.alert type="success" :autodismiss="true" :message="session('status')" />
        @endif
        @if (session('error'))
            <x-authentication::feedback.alert type="error" :autodismiss="true" :message="session('error')" />
        @elseif ($errors->any())
            <x-authentication::feedback.alert type="error" :autodismiss="true" :message="$errors->first()" />
        @endif

        <form method="POST" action="{{ $updateRoute }}" class="space-y-4" novalidate
              x-data="{ submitting: false }"
              @submit="submitting = true">
            @csrf

            <input type="hidden" name="token" value="{{ $token ?? request()->route('token') }}">

            @php
                $displayEmail = $email ?? '';
                $displayName = $name ?? '';
                $emailMax = (int) config('authentication.ui.input_maxlength', 255);
                $nameMax = (int) config('authentication.ui.name_maxlength', 60);
                $emailTrunc = $displayEmail !== '' && mb_strlen($displayEmail) > $emailMax ? mb_substr($displayEmail, 0, $emailMax) : $displayEmail;
                $nameTrunc = $displayName !== '' && mb_strlen($displayName) > $nameMax ? mb_substr($displayName, 0, $nameMax) : $displayName;
            @endphp

            {{-- Display-only user identity: do not allow changing email --}}
            @if ($displayEmail !== '')
                <div class="space-y-1">
                    <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300">{{ __('authentication::messages.email_label') }}</label>
                    <input
                        type="email"
                        readonly
                        disabled
                        class="block w-full rounded-lg border border-zinc-200 dark:border-zinc-700 bg-zinc-100 dark:bg-zinc-800 text-zinc-600 dark:text-zinc-400 px-3 py-2 text-sm cursor-not-allowed"
                        value="{{ $emailTrunc }}"
                        maxlength="{{ $emailMax }}"
                        aria-readonly="true"
                    >
                    <input type="hidden" name="email" value="{{ $emailTrunc }}">
                </div>
            @endif

            @if ($nameTrunc !== '')
                <div class="space-y-1">
                    <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300">{{ __('authentication::messages.attribute_name') }}</label>
                    <input
                        type="text"
                        readonly
                        disabled
                        class="block w-full rounded-lg border border-zinc-200 dark:border-zinc-700 bg-zinc-100 dark:bg-zinc-800 text-zinc-600 dark:text-zinc-400 px-3 py-2 text-sm cursor-not-allowed"
                        value="{{ $nameTrunc }}"
                        maxlength="{{ $nameMax }}"
                        aria-readonly="true"
                    >
                </div>
            @endif

            <x-authentication::forms.input
                name="password"
                type="password"
                :label="__('authentication::messages.new_password_label')"
                :placeholder="__('authentication::messages.new_password_placeholder')"
                :required="true"
                autocomplete="new-password"
                :autofocus="true"
            />

            <x-authentication::forms.input
                name="password_confirmation"
                type="password"
                :label="__('authentication::messages.confirm_password_label')"
                                :placeholder="__('authentication::messages.confirm_password_placeholder')"
                :required="true"
                autocomplete="new-password"
            />

            <div class="pt-2">
                <x-authentication::forms.button type="submit" variant="primary">
                    {{ __('authentication::messages.reset_btn') }}
                </x-authentication::forms.button>
            </div>

        </form>

        <div class="pt-4 border-t border-zinc-100 dark:border-zinc-800 text-center text-xs">
            <a href="{{ $loginRoute }}" class="auth-link hover:underline">
                &larr; {{ __('authentication::messages.back_to_login_arrow') }}
            </a>
        </div>

    </div>

</x-dynamic-component>
