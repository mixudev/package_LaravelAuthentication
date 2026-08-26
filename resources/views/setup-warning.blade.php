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
                            DEFAULT: '#1e293b', // slate-800, single soft-dark accent
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
        @if($errorCount === 0 && $warningCount === 0)
        <div class="text-center">
            <div class="text-2xl font-semibold text-slate-900">All clear</div>
            <div class="mt-0.5 text-xs uppercase tracking-wide text-slate-400">No issues detected</div>
        </div>
        @endif
    </div>

    {{-- Issues --}}
    @if(count($issues) > 0)

        @php
            $categoryMeta = [
                'security' => 'Security',
                'database' => 'Database',
                'package'  => 'Required packages',
                'oauth'    => 'OAuth / social login',
                'mail'     => 'Mail / SMTP',
                'config'   => 'Configuration',
            ];
            $categoryOrder = ['security', 'database', 'package', 'oauth', 'mail', 'config'];
        @endphp

        @foreach($categoryOrder as $cat)
            @if(isset($grouped[$cat]) && count($grouped[$cat]) > 0)
                @php $label = $categoryMeta[$cat] ?? ucfirst($cat); @endphp

                <div class="mt-10 mb-1 flex items-baseline justify-between border-b border-slate-200 pb-2">
                    <h2 class="text-xs font-medium uppercase tracking-wide text-slate-400">{{ $label }}</h2>
                    <span class="text-xs text-slate-400">{{ count($grouped[$cat]) }} {{ Str::plural('issue', count($grouped[$cat])) }}</span>
                </div>

                @foreach($grouped[$cat] as $issue)
                @php
                    $cardId = 'issue-' . $cat . '-' . $loop->index;
                    $bodyId = 'body-' . $cat . '-' . $loop->index;
                    $expanded = $issue->isError() ? 'true' : 'false';
                @endphp
                <div id="{{ $cardId }}" class="border-b border-slate-100" data-expanded="{{ $expanded }}">
                    <button type="button"
                            onclick="toggleIssue('{{ $cardId }}')"
                            class="flex w-full items-center justify-between gap-4 py-4 text-left"
                            aria-expanded="{{ $expanded }}"
                            aria-controls="{{ $bodyId }}">
                        <span class="min-w-0">
                            <span class="block truncate text-sm font-medium {{ $issue->isError() ? 'text-slate-900' : 'text-slate-700' }}">
                                {{ $issue->title }}
                            </span>
                        </span>
                        <span class="flex shrink-0 items-center gap-3">
                            <span class="text-xs uppercase tracking-wide {{ $issue->isError() ? 'font-medium text-slate-900' : 'text-slate-400' }}">
                                {{ $issue->isError() ? 'Error' : 'Warning' }}
                            </span>
                            <svg class="chevron h-3.5 w-3.5 text-slate-400 transition-transform" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <polyline points="6 9 12 15 18 9"></polyline>
                            </svg>
                        </span>
                    </button>

                    <div id="{{ $bodyId }}" class="issue-body {{ $issue->isError() ? '' : 'hidden' }} pb-5">
                        <p class="mb-3 text-sm leading-relaxed text-slate-500">{{ $issue->description }}</p>

                        <div class="border border-slate-200">
                            <div class="flex items-center justify-between border-b border-slate-200 px-3 py-1.5">
                                <span class="text-xs uppercase tracking-wide text-slate-400">Fix</span>
                                <button type="button"
                                        class="copy-btn text-xs text-slate-400 hover:text-slate-900"
                                        id="copy-btn-{{ $cardId }}"
                                        onclick="copyFix('{{ $cardId }}', {{ json_encode($issue->fix) }})">
                                    Copy
                                </button>
                            </div>
                            <pre class="overflow-x-auto px-3 py-3 font-mono text-xs leading-relaxed text-slate-700">{{ $issue->fix }}</pre>
                        </div>
                    </div>
                </div>
                @endforeach
            @endif
        @endforeach

    @else
        {{-- Empty state --}}
        <div class="py-16 text-center">
            <h2 class="text-base font-medium">Everything looks good</h2>
            <p class="mx-auto mt-1 max-w-xs text-sm text-slate-500">No configuration issues were found.</p>
            <a href="{{ url('/login') }}" class="mt-6 inline-block bg-accent px-5 py-2.5 text-sm text-white hover:bg-accent-light">
                Go to login
            </a>
        </div>
    @endif

    {{-- Actions --}}
    @if(count($issues) > 0)
    <div class="flex flex-wrap gap-3 py-10">
        <button onclick="window.location.reload()" class="bg-accent px-5 py-2.5 text-sm text-white hover:bg-accent-light">
            Re-check configuration
        </button>
        <a href="https://github.com/mixudev/laravel-authentication" target="_blank" rel="noopener noreferrer"
           class="border border-slate-300 px-5 py-2.5 text-sm text-slate-700 hover:bg-slate-50">
            Documentation
        </a>
    </div>
    @endif

    {{-- Footer --}}
    <div class="flex items-center justify-between border-t border-slate-200 py-6 text-xs text-slate-400">
        <span>mixudev/laravel-authentication — setup check</span>
        <a href="https://github.com/mixudev/laravel-authentication#configuration" target="_blank" rel="noopener noreferrer" class="hover:text-slate-700">
            Full config docs →
        </a>
    </div>

</div>

<script>
    function toggleIssue(cardId) {
        const card = document.getElementById(cardId);
        if (!card) return;
        const body = card.querySelector('.issue-body');
        const btn = card.querySelector('button[aria-controls]');
        const chevron = card.querySelector('.chevron');
        const isHidden = body.classList.toggle('hidden');
        btn.setAttribute('aria-expanded', isHidden ? 'false' : 'true');
        chevron.style.transform = isHidden ? 'rotate(0deg)' : 'rotate(180deg)';
    }

    function copyFix(cardId, text) {
        const btn = document.getElementById('copy-btn-' + cardId);
        if (!btn) return;
        const original = btn.textContent;

        function done() {
            btn.textContent = 'Copied';
            setTimeout(() => { btn.textContent = original; }, 1500);
        }

        if (navigator.clipboard) {
            navigator.clipboard.writeText(text).then(done).catch(fallback);
        } else {
            fallback();
        }

        function fallback() {
            const el = document.createElement('textarea');
            el.value = text;
            el.style.position = 'absolute';
            el.style.opacity = '0';
            document.body.appendChild(el);
            el.select();
            document.execCommand('copy');
            document.body.removeChild(el);
            done();
        }
    }

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('[data-expanded="true"] .chevron').forEach(function (c) {
            c.style.transform = 'rotate(180deg)';
        });
    });
</script>
</body>
</html>