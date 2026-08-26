{{-- 
=============================================================================
LAYOUT UTAMA: BASE AUTHENTICATION
Package: mixudev/laravel-authentication
Deskripsi: Kerangka dasar HTML5 universal dengan dukungan penuh Light/Dark mode,
           kontras tinggi pada layar IPS, dan deteksi logo otomatis.
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

    {{-- Font Bersih & Standar Laravel --}}
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

        /* Penegakan Gaya Light Mode (Kontras Kuat di Layar IPS) */
        html.light body, html:not(.dark) body {
            background-color: #f1f5f9 !important; /* Slate-100 kontras jelas */
            color: #0f172a !important;
        }
        html.light .auth-card, html:not(.dark) .auth-card {
            background-color: #ffffff !important;
            border-color: #cbd5e1 !important; /* Border tebal & tegas */
            color: #0f172a !important;
            box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.07), 0 2px 4px -2px rgb(0 0 0 / 0.07) !important;
        }
        html.light .auth-heading, html:not(.dark) .auth-heading {
            color: #0f172a !important;
        }
        html.light .auth-subtext, html:not(.dark) .auth-subtext {
            color: #64748b !important;
        }
        html.light .auth-label, html:not(.dark) .auth-label {
            color: #1e293b !important;
        }
        html.light .auth-input, html:not(.dark) .auth-input {
            background-color: #ffffff !important;
            border-color: #cbd5e1 !important;
            color: #0f172a !important;
        }
        html.light .auth-input::placeholder, html:not(.dark) .auth-input::placeholder {
            color: #94a3b8 !important;
        }
        html.light .auth-input:focus, html:not(.dark) .auth-input:focus {
            border-color: #0f172a !important;
            box-shadow: 0 0 0 1px #0f172a !important;
        }
        html.light .auth-btn-primary, html:not(.dark) .auth-btn-primary {
            background-color: #0f172a !important;
            color: #ffffff !important;
        }
        html.light .auth-btn-primary:hover, html:not(.dark) .auth-btn-primary:hover {
            background-color: #1e293b !important;
        }
        html.light .auth-btn-secondary, html:not(.dark) .auth-btn-secondary {
            background-color: #ffffff !important;
            border-color: #cbd5e1 !important;
            color: #0f172a !important;
        }
        html.light .auth-btn-secondary:hover, html:not(.dark) .auth-btn-secondary:hover {
            background-color: #f8fafc !important;
        }
        html.light .auth-link, html:not(.dark) .auth-link {
            color: #334155 !important;
        }
        html.light .auth-link:hover, html:not(.dark) .auth-link:hover {
            color: #0f172a !important;
        }
        html.light .auth-divider, html:not(.dark) .auth-divider {
            border-color: #cbd5e1 !important;
        }
        html.light .auth-divider-text, html:not(.dark) .auth-divider-text {
            background-color: #ffffff !important;
            color: #64748b !important;
        }
        html.light .auth-checkbox-label, html:not(.dark) .auth-checkbox-label {
            color: #334155 !important;
        }
        html.light .auth-otp-input, html:not(.dark) .auth-otp-input {
            background-color: #ffffff !important;
            border-color: #cbd5e1 !important;
            color: #0f172a !important;
        }
        html.light .auth-otp-input:focus, html:not(.dark) .auth-otp-input:focus {
            border-color: #0f172a !important;
            box-shadow: 0 0 0 1px #0f172a !important;
        }
        html.light .auth-eye-btn, html:not(.dark) .auth-eye-btn {
            color: #94a3b8 !important;
        }
        html.light .auth-eye-btn:hover, html:not(.dark) .auth-eye-btn:hover {
            color: #475569 !important;
        }
        html.light .auth-field-error, html:not(.dark) .auth-field-error {
            color: #dc2626 !important;
        }
        html.light .auth-alert-success, html:not(.dark) .auth-alert-success {
            background-color: #f0fdf4 !important;
            border-color: #bbf7d0 !important;
            color: #166534 !important;
        }
        html.light .auth-alert-error, html:not(.dark) .auth-alert-error {
            background-color: #fef2f2 !important;
            border-color: #fecaca !important;
            color: #991b1b !important;
        }
        html.light .auth-alert-warning, html:not(.dark) .auth-alert-warning {
            background-color: #fffbeb !important;
            border-color: #fde68a !important;
            color: #92400e !important;
        }
        html.light .auth-alert-info, html:not(.dark) .auth-alert-info {
            background-color: #eff6ff !important;
            border-color: #bfdbfe !important;
            color: #1e40af !important;
        }

        /* Penegakan Gaya Dark Mode Pekat */
        html.dark body {
            background-color: #09090b !important;
            color: #f8fafc !important;
        }
        html.dark .auth-alert-success {
            background-color: #052e16 !important;
            border-color: #166534 !important;
            color: #86efac !important;
        }
        html.dark .auth-alert-error {
            background-color: #450a0a !important;
            border-color: #991b1b !important;
            color: #fca5a5 !important;
        }
        html.dark .auth-alert-warning {
            background-color: #451a03 !important;
            border-color: #b45309 !important;
            color: #fcd34d !important;
        }
        html.dark .auth-alert-info {
            background-color: #172554 !important;
            border-color: #1d4ed8 !important;
            color: #93c5fd !important;
        }
        html.dark .auth-otp-input {
            background-color: #09090b !important;
            border-color: #27272a !important;
            color: #f8fafc !important;
        }
        html.dark .auth-otp-input:focus {
            border-color: #a1a1aa !important;
            box-shadow: 0 0 0 1px #a1a1aa !important;
        }
        html.dark .auth-eye-btn {
            color: #52525b !important;
        }
        html.dark .auth-eye-btn:hover {
            color: #a1a1aa !important;
        }
        html.dark .auth-field-error {
            color: #f87171 !important;
        }
        html.dark .auth-card {
            background-color: #121215 !important;
            border-color: #27272a !important;
            color: #f8fafc !important;
            box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.5) !important;
        }
        html.dark .auth-heading {
            color: #f8fafc !important;
        }
        html.dark .auth-subtext {
            color: #a1a1aa !important;
        }
        html.dark .auth-label {
            color: #d4d4d8 !important;
        }
        html.dark .auth-input {
            background-color: #09090b !important;
            border-color: #27272a !important;
            color: #f8fafc !important;
        }
        html.dark .auth-input::placeholder {
            color: #52525b !important;
        }
        html.dark .auth-input:focus {
            border-color: #a1a1aa !important;
            box-shadow: 0 0 0 1px #a1a1aa !important;
        }
        html.dark .auth-btn-primary {
            background-color: #f8fafc !important;
            color: #09090b !important;
        }
        html.dark .auth-btn-primary:hover {
            background-color: #ffffff !important;
        }
        html.dark .auth-btn-secondary {
            background-color: #18181b !important;
            border-color: #27272a !important;
            color: #f8fafc !important;
        }
        html.dark .auth-btn-secondary:hover {
            background-color: #27272a !important;
        }
        html.dark .auth-link {
            color: #a1a1aa !important;
        }
        html.dark .auth-link:hover {
            color: #f8fafc !important;
        }
        html.dark .auth-divider {
            border-color: #27272a !important;
        }
        html.dark .auth-divider-text {
            background-color: #121215 !important;
            color: #71717a !important;
        }
        html.dark .auth-checkbox-label {
            color: #a1a1aa !important;
        }
    </style>

    @stack('styles')
</head>
<body class="font-sans antialiased min-h-screen">
    
    {{ $slot ?? '' }}
    @yield('content')

    @stack('scripts')
</body>
</html>
