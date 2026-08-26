{{-- 
=============================================================================
LAYOUT UTAMA: BASE AUTHENTICATION (DEEP DARK & CRISP LIGHT)
Package: mixudev/laravel-authentication
Deskripsi: Kerangka dasar HTML5 bersih, pekat, modern, dan elegan.
           Mendukung deteksi favicon aplikasi otomatis, tema zinc pekat, dan
           penegakan tema eksplisit (mengatasi override dark browser).
=============================================================================
--}}
@props([
    'title' => null,
    'metaRobots' => 'noindex, nofollow',
])

@php
    $brandName = config('authentication.ui.brand_name', config('app.name', 'Laravel'));
    $appTitle = $title ? "{$title} — {$brandName}" : $brandName;
    $themeConfig = config('authentication.ui.theme', 'dark');
    $isDark = ($themeConfig === 'dark');
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="{{ $isDark ? 'dark' : 'light' }}" data-theme="{{ $isDark ? 'dark' : 'light' }}" style="color-scheme: {{ $isDark ? 'dark' : 'light' }};">
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

    {{-- Typography Modern & Bersih --}}
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet" />

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
                        },
                        colors: {
                            zinc: {
                                950: '#09090b',
                                900: '#18181b',
                                850: '#202024',
                                800: '#27272a',
                                700: '#3f3f46',
                                600: '#52525b',
                                500: '#71717a',
                                400: '#a1a1aa',
                                300: '#d4d4d8',
                                200: '#e4e4e7',
                                100: '#f4f4f5',
                                50: '#fafafa',
                            }
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

        {{-- Penegakan Tema: Memastikan Light Mode tetap aktif meskipun browser user diset Dark Mode --}}
        @if (!$isDark)
        html:not(.dark), html.light {
            color-scheme: light !important;
        }
        html:not(.dark) body {
            background-color: #fafafa !important;
            color: #09090b !important;
        }
        html:not(.dark) .auth-card {
            background-color: #ffffff !important;
            border-color: #e4e4e7 !important;
            color: #09090b !important;
        }
        html:not(.dark) .auth-input {
            background-color: #ffffff !important;
            border-color: #d4d4d8 !important;
            color: #09090b !important;
        }
        html:not(.dark) .auth-input:focus {
            border-color: #18181b !important;
            --tw-ring-color: #18181b !important;
        }
        html:not(.dark) .auth-btn-primary {
            background-color: #18181b !important;
            color: #ffffff !important;
        }
        html:not(.dark) .auth-btn-primary:hover {
            background-color: #27272a !important;
        }
        html:not(.dark) .auth-btn-secondary {
            background-color: #ffffff !important;
            border-color: #e4e4e7 !important;
            color: #18181b !important;
        }
        html:not(.dark) .auth-btn-secondary:hover {
            background-color: #f4f4f5 !important;
        }
        @else
        {{-- Penegakan Tema Pekat untuk Dark Mode --}}
        html.dark body {
            background-color: #09090b !important;
            color: #f4f4f5 !important;
        }
        html.dark .auth-card {
            background-color: #121215 !important;
            border-color: #27272a !important;
            color: #f4f4f5 !important;
        }
        html.dark .auth-input {
            background-color: #09090b !important;
            border-color: #27272a !important;
            color: #f4f4f5 !important;
        }
        html.dark .auth-input:focus {
            border-color: #52525b !important;
            --tw-ring-color: #52525b !important;
        }
        html.dark .auth-btn-primary {
            background-color: #f4f4f5 !important;
            color: #09090b !important;
        }
        html.dark .auth-btn-primary:hover {
            background-color: #ffffff !important;
        }
        html.dark .auth-btn-secondary {
            background-color: #18181b !important;
            border-color: #27272a !important;
            color: #f4f4f5 !important;
        }
        html.dark .auth-btn-secondary:hover {
            background-color: #27272a !important;
        }
        @endif
    </style>

    @stack('styles')
</head>
<body class="font-sans antialiased text-zinc-900 dark:text-zinc-100 bg-zinc-50 dark:bg-zinc-950 min-h-screen transition-colors duration-150">
    
    {{ $slot ?? '' }}
    @yield('content')

    @stack('scripts')
</body>
</html>
