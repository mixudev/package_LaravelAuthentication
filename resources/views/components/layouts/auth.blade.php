{{-- 
=============================================================================
LAYOUT UTAMA: BASE AUTHENTICATION
Package: mixudev/laravel-authentication
Deskripsi: Kerangka dasar HTML5, memuat aset Vite / Tailwind CSS, meta CSRF,
           serta konfigurasi font & tema keamanan standar enterprise.
=============================================================================
--}}
@props([
    'title' => null,
    'metaRobots' => 'noindex, nofollow',
])

@php
    $appTitle = $title 
        ? $title . ' — ' . config('authentication.ui.brand_name', config('app.name', 'Console Auth'))
        : config('authentication.ui.brand_name', config('app.name', 'Console Auth'));
    $theme = config('authentication.ui.theme', 'dark');
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="{{ $theme === 'dark' ? 'dark' : '' }} h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    
    {{-- Keamanan: Cegah indexing mesin pencari pada halaman sensitif autentikasi --}}
    <meta name="robots" content="{{ $metaRobots }}">
    
    {{-- Token CSRF untuk keamanan request AJAX / Fetch --}}
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $appTitle }}</title>

    {{-- Typography Modern: Inter (UI), Space Grotesk (Heading), IBM Plex Mono (Badges & Code) --}}
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Mono:wght@400;500;600&family=Inter:wght@300;400;500;600;700&family=Space+Grotesk:wght@500;600;700&display=swap" rel="stylesheet">

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
                            sans: ['Inter', 'sans-serif'],
                            heading: ['Space Grotesk', 'sans-serif'],
                            mono: ['IBM Plex Mono', 'monospace'],
                        },
                        colors: {
                            brand: {
                                50: '#fffbeb',
                                100: '#fef3c7',
                                400: '#fbbf24',
                                500: '#f59e0b',
                                600: '#d97706',
                                accent: '#e8a33d',
                            }
                        }
                    }
                }
            }
        </script>
    @endif

    <style>
        html, body {
            height: 100%;
            margin: 0;
            padding: 0;
        }
        body {
            font-family: 'Inter', sans-serif;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }
        h1, h2, h3, .font-heading {
            font-family: 'Space Grotesk', sans-serif;
        }
        .font-mono-code {
            font-family: 'IBM Plex Mono', monospace;
        }
    </style>

    @stack('styles')
</head>
<body class="min-h-screen w-full bg-slate-50 text-slate-900 dark:bg-slate-950 dark:text-slate-100 antialiased selection:bg-amber-500/30 selection:text-amber-800 dark:selection:text-amber-200 overflow-x-hidden">
    
    {{-- Slot Utama untuk Rendering Halaman / Sub-Layout --}}
    {{ $slot ?? '' }}
    @yield('content')

    @stack('scripts')
</body>
</html>
