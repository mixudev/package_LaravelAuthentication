{{-- 
=============================================================================
LAYOUT UTAMA: BASE AUTHENTICATION
Package: mixudev/laravel-authentication
Deskripsi: Kerangka dasar HTML5 universal dengan dukungan penuh Light/Dark/Auto mode,
           kontras tinggi, Alpine.js terintegrasi, dan deteksi Vite/Tailwind otomatis.
=============================================================================
--}}
@props([
    'title' => null,
    'metaRobots' => 'noindex, nofollow',
])

@php
    $brandName = config('authentication.ui.brand_name') ?: config('app.name', 'Laravel');
    $appTitle = $title ? "{$title} — {$brandName}" : $brandName;
    $themeConfig = config('authentication.ui.theme', 'light');
    $initialDark = ($themeConfig === 'dark');
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="{{ $initialDark ? 'dark' : 'light' }}" data-theme="{{ $initialDark ? 'dark' : 'light' }}" style="color-scheme: {{ $initialDark ? 'dark' : 'light' }};">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <meta name="robots" content="{{ $metaRobots }}">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $appTitle }}</title>

    {{-- Favicon Bawaan Host Application --}}
    @if (file_exists(public_path('favicon.svg')))
        <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    @elseif (file_exists(public_path('favicon.ico')))
        <link rel="icon" type="image/x-icon" href="{{ asset('favicon.ico') }}">
        <link rel="shortcut icon" href="{{ asset('favicon.ico') }}">
    @endif

    {{-- Font Bersih & Standar Laravel --}}
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet" />

    {{-- Theme Engine Script: Light, Dark, atau Auto (Sesuai OS/Browser) --}}
    <script>
        (function() {
            const themeConfig = '{{ $themeConfig }}';
            const html = document.documentElement;

            function updateTheme(isDark) {
                if (isDark) {
                    html.classList.add('dark');
                    html.classList.remove('light');
                    html.setAttribute('data-theme', 'dark');
                    html.style.colorScheme = 'dark';
                } else {
                    html.classList.remove('dark');
                    html.classList.add('light');
                    html.setAttribute('data-theme', 'light');
                    html.style.colorScheme = 'light';
                }
            }

            if (themeConfig === 'auto') {
                const mediaQuery = window.matchMedia('(prefers-color-scheme: dark)');
                updateTheme(mediaQuery.matches);
                try {
                    mediaQuery.addEventListener('change', (e) => updateTheme(e.matches));
                } catch (err) {
                    // Fallback browser lama
                    mediaQuery.addListener((e) => updateTheme(e.matches));
                }
            } else {
                updateTheme(themeConfig === 'dark');
            }
        })();
    </script>

    {{-- Alpine.js Standar untuk Interaktivitas Modal & Komponen --}}
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>

    {{-- Asset Bundler: Otomatis mendeteksi Vite Host Application dengan safe fallback Tailwind --}}
    @if (config('authentication.ui.use_vite', true) && (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot'))))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @else
        <script src="https://cdn.tailwindcss.com"></script>
        <script>
            tailwind.config = {
                darkMode: 'class',
                theme: {
                    extend: {
                        fontFamily: {
                            sans: ['Figtree', 'Inter', 'ui-sans-serif', 'system-ui', 'sans-serif'],
                        }
                    }
                }
            }
        </script>
    @endif

    <style>
        body {
            font-family: 'Figtree', 'Inter', ui-sans-serif, system-ui, sans-serif;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }

        /* Default Styling Card & Form Helper */
        html.light body {
            background-color: #f8fafc;
            color: #0f172a;
        }
        html.light .auth-card {
            background-color: #ffffff;
            border-color: #e2e8f0;
            color: #0f172a;
            box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.05), 0 2px 4px -2px rgb(0 0 0 / 0.05);
        }
        html.light .auth-input {
            background-color: #ffffff;
            border-color: #cbd5e1;
            color: #0f172a;
        }
        html.light .auth-input:focus {
            border-color: #0f172a;
            box-shadow: 0 0 0 1px #0f172a;
        }
        html.light .auth-btn-primary {
            background-color: #0f172a;
            color: #ffffff;
        }
        html.light .auth-btn-primary:hover {
            background-color: #1e293b;
        }

        /* Auth Divider (OR separator line) */
        html.light .auth-divider {
            border-color: #e2e8f0;
        }
        html.light .auth-divider-text {
            background-color: #ffffff;
            color: #94a3b8;
        }

        html.dark body {
            background-color: #09090b;
            color: #f8fafc;
        }
        html.dark .auth-card {
            background-color: #121215;
            border-color: #27272a;
            color: #f8fafc;
            box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.5);
        }
        html.dark .auth-input {
            background-color: #09090b;
            border-color: #27272a;
            color: #f8fafc;
        }
        html.dark .auth-input:focus {
            border-color: #a1a1aa;
            box-shadow: 0 0 0 1px #a1a1aa;
        }
        html.dark .auth-btn-primary {
            background-color: #f8fafc;
            color: #09090b;
        }
        html.dark .auth-btn-primary:hover {
            background-color: #ffffff;
        }

        /* Dark Mode Divider */
        html.dark .auth-divider {
            border-color: #27272a;
        }
        html.dark .auth-divider-text {
            background-color: #121215;
            color: #52525b;
        }

        /* ============================================================
           ALERT COLORS — Light Mode
           ============================================================ */
        html.light .auth-alert-success, html:not(.dark) .auth-alert-success {
            background-color: #f0fdf4;
            border-color: #bbf7d0;
            color: #15803d;
        }
        html.light .auth-alert-error, html:not(.dark) .auth-alert-error {
            background-color: #fff1f2;
            border-color: #fda4af;
            color: #be123c;
        }
        html.light .auth-alert-warning, html:not(.dark) .auth-alert-warning {
            background-color: #fffbeb;
            border-color: #fde68a;
            color: #92400e;
        }
        html.light .auth-alert-info, html:not(.dark) .auth-alert-info {
            background-color: #eff6ff;
            border-color: #bfdbfe;
            color: #1d4ed8;
        }

        /* ============================================================
           ALERT COLORS — Dark Mode
           ============================================================ */
        html.dark .auth-alert-success {
            background-color: #052e16;
            border-color: #166534;
            color: #86efac;
        }
        html.dark .auth-alert-error {
            background-color: #4c0519;
            border-color: #9f1239;
            color: #fda4af;
        }
        html.dark .auth-alert-warning {
            background-color: #451a03;
            border-color: #b45309;
            color: #fcd34d;
        }
        html.dark .auth-alert-info {
            background-color: #172554;
            border-color: #1d4ed8;
            color: #93c5fd;
        }

        /* ============================================================
           INPUT ERROR STATE — Border merah tetap ada saat focus
           ============================================================ */
        html.light .auth-input.input-error,
        html:not(.dark) .auth-input.input-error {
            border-color: #f43f5e;
            box-shadow: none;
        }
        html.light .auth-input.input-error:focus,
        html:not(.dark) .auth-input.input-error:focus {
            border-color: #f43f5e;
            box-shadow: 0 0 0 1px #f43f5e;
        }
        html.dark .auth-input.input-error {
            border-color: #f43f5e;
            box-shadow: none;
        }
        html.dark .auth-input.input-error:focus {
            border-color: #fb7185;
            box-shadow: 0 0 0 1px #fb7185;
        }

        /* Error message text color */
        .auth-field-error {
            color: #e11d48;
        }
        html.dark .auth-field-error {
            color: #fb7185;
        }

        /* ============================================================
           MODERN SOCIAL & PASSKEY BUTTONS — Flex, Icon Left, Text Right
           ============================================================ */
        .auth-btn-social {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            width: 100%;
            font-weight: 500;
            font-size: 0.8125rem;
            letter-spacing: 0.005em;
            padding: 0.625rem 1rem;
            border-radius: 0.5rem;
            transition: all 0.15s ease;
            position: relative;
            user-select: none;
            cursor: pointer;
            text-decoration: none;
            white-space: nowrap;
            outline: none;
        }
        .auth-btn-social:hover {
            transform: translateY(-1px);
        }
        .auth-btn-social:active {
            transform: translateY(0);
            opacity: 0.88;
        }
        .auth-btn-social:focus-visible {
            outline: 2px solid #3b82f6;
            outline-offset: 2px;
        }

        /* Icon: fixed size, flex-shrink 0 agar tidak gepeng */
        .auth-btn-social .auth-btn-icon {
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            width: 16px;
            height: 16px;
        }

        /* Label text */
        .auth-btn-social .auth-btn-label {
            line-height: 1;
        }

        /* Light Mode */
        html.light .auth-btn-social,
        html:not(.dark) .auth-btn-social {
            background-color: #ffffff;
            border: 1.5px solid #d1d5db;
            color: #111827;
            box-shadow: 0 1px 3px 0 rgb(0 0 0 / 0.06), 0 1px 2px -1px rgb(0 0 0 / 0.04);
        }
        html.light .auth-btn-social:hover,
        html:not(.dark) .auth-btn-social:hover {
            background-color: #f9fafb;
            border-color: #9ca3af;
            box-shadow: 0 4px 12px -2px rgb(0 0 0 / 0.1);
            color: #030712;
        }
        html.light .auth-btn-social-google:hover,
        html:not(.dark) .auth-btn-social-google:hover {
            border-color: #4285F4;
            box-shadow: 0 4px 14px -2px rgb(66 133 244 / 0.18);
        }
        html.light .auth-btn-social-github:hover,
        html:not(.dark) .auth-btn-social-github:hover {
            border-color: #374151;
            box-shadow: 0 4px 14px -2px rgb(15 23 42 / 0.14);
        }
        html.light .auth-btn-passkey:hover,
        html:not(.dark) .auth-btn-passkey:hover {
            border-color: #3b82f6;
            box-shadow: 0 4px 14px -2px rgb(59 130 246 / 0.18);
        }

        /* Dark Mode */
        html.dark .auth-btn-social {
            background-color: #1c1c1e;
            border: 1.5px solid #3a3a3c;
            color: #f2f2f7;
            box-shadow: 0 1px 3px 0 rgb(0 0 0 / 0.5), inset 0 1px 0 rgb(255 255 255 / 0.04);
        }
        html.dark .auth-btn-social:hover {
            background-color: #2c2c2e;
            border-color: #636366;
            box-shadow: 0 4px 16px -2px rgb(0 0 0 / 0.65);
            color: #ffffff;
        }
        html.dark .auth-btn-social-google:hover {
            border-color: #4285F4;
            box-shadow: 0 4px 16px -2px rgb(66 133 244 / 0.22);
        }
        html.dark .auth-btn-social-github:hover {
            border-color: #8e8e93;
            box-shadow: 0 4px 16px -2px rgb(255 255 255 / 0.1);
        }
        html.dark .auth-btn-passkey:hover {
            border-color: #60a5fa;
            box-shadow: 0 4px 16px -2px rgb(96 165 250 / 0.28);
        }

    @stack('styles')
</head>
<body class="font-sans antialiased min-h-screen bg-slate-50 dark:bg-zinc-950 text-slate-900 dark:text-zinc-100 transition-colors duration-150">
    
    {{ $slot ?? '' }}
    @yield('content')

    @stack('scripts')
</body>
</html>
