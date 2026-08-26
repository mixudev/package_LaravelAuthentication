<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Authentication Setup Required — {{ $appName }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        accent: {
                            DEFAULT: '#1e293b',
                            light: '#475569',
                        }
                    }
                }
            }
        }
    </script>
</head>
<body class="min-h-screen bg-white text-slate-900 antialiased">

{{-- Environment notice --}}
<div class="border-b border-slate-200 bg-slate-50 py-2 text-center text-xs text-slate-500">
    This page is only visible in non-production environments —
    <span class="font-medium text-slate-700">{{ strtoupper($environment) }}</span>
</div>

<div class="mx-auto max-w-2xl px-6">

    {{-- Header --}}
    <div class="pb-10 pt-16 text-center">
        <div class="mx-auto mb-6 flex h-12 w-12 items-center justify-center rounded-full border border-slate-300">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" class="h-5 w-5 text-slate-700">
                <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path>
                <line x1="12" y1="9" x2="12" y2="13"></line>
                <line x1="12" y1="17" x2="12.01" y2="17"></line>
            </svg>
        </div>
        <h1 class="text-xl font-semibold tracking-tight">Authentication setup required</h1>
        <p class="mx-auto mt-2 max-w-md text-sm leading-relaxed text-slate-500">
            {{ $appName }} found configuration issues that need to be fixed before authentication will work.
        </p>
    </div>

    {{-- Summary --}}
    <div class="flex items-center justify-center gap-10 border-y border-slate-200 py-5">
        @if($errorCount > 0)
        <div class="text-center">
            <div class="text-2xl font-semibold text-slate-900">{{ $errorCount }}</div>
            <div class="mt-0.5 text-xs uppercase tracking-wide text-slate-400">{{ Str::plural('error', $errorCount) }}</div>
        </div>
        @endif
        @if($warningCount > 0)
        <div class="text-center">
            <div class="text-2xl font-semibold text-slate-500">{{ $warningCount }}</div>
            <div class="mt-0.5 text-xs uppercase tracking-wide text-slate-400">{{ Str::plural('warning', $warningCount) }}</div>
        </div>
        @endif
    </div>

    {{-- Issues list --}}
    <div class="divide-y divide-slate-100 py-8">
        @foreach($issues as $issue)
        <div class="py-6 first:pt-0 last:pb-0">
            <div class="flex items-baseline justify-between gap-4">
                <span class="text-xs font-medium uppercase tracking-wider {{ $issue['level'] === 'error' ? 'text-slate-900' : 'text-slate-400' }}">
                    {{ $issue['level'] }}
                </span>
                <span class="text-xs text-slate-400 font-mono">{{ $issue['category'] }}</span>
            </div>

            <h2 class="mt-1.5 text-sm font-semibold text-slate-900">
                {{ $issue['title'] }}
            </h2>

            <p class="mt-1 text-sm leading-relaxed text-slate-600">
                {{ $issue['message'] }}
            </p>

            @if(!empty($issue['fix']))
            <div class="mt-3 rounded border border-slate-200 bg-slate-50 px-3.5 py-2.5">
                <div class="text-xs font-medium text-slate-500 uppercase tracking-wide">Fix</div>
                <div class="mt-0.5 text-xs text-slate-700 leading-relaxed font-mono">
                    {{ $issue['fix'] }}
                </div>
            </div>
            @endif
        </div>
        @endforeach
    </div>

    {{-- Footer --}}
    <div class="border-t border-slate-200 py-8 text-center text-xs text-slate-400">
        <p>This message will disappear once configuration issues are resolved.</p>
        <p class="mt-1">In production (<code class="font-mono text-slate-600">APP_ENV=production</code>), this screen is disabled and a 500 error is thrown.</p>
    </div>

</div>

</body>
</html>
