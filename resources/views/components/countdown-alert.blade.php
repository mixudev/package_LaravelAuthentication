{{-- 
=============================================================================
KOMPONEN: COUNTDOWN ALERT (UX ENHANCEMENT)
Package: mixudev/laravel-authentication
Deskripsi: Alert dengan live countdown timer untuk throttle/lockout.
           Menghitung mundur secara real-time, menampilkan progress bar,
           mendisable tombol submit form, dan auto-enable kembali saat habis.
=============================================================================
--}}
@props([
    'type'          => 'error',
    'message'       => null,
    'retryAfter'    => null, // Integer detik, atau null untuk auto-detect dari message
    'submitButton'  => '#submit-btn', // CSS selector untuk tombol yang di-disable
])

@php
    // Auto-detect seconds dari message jika retryAfter null
    $seconds = $retryAfter;
    if ($seconds === null && $message) {
        // Ekstrak angka dari pola: "in 45 seconds", "dalam 60 detik", "try again in 120 seconds"
        if (preg_match('/(\d+)\s*(second|detik|menit|minute)/i', $message, $matches)) {
            $seconds = (int) $matches[1];
            // Jika unit adalah menit, konversi ke detik
            if (stripos($matches[2], 'menit') !== false || stripos($matches[2], 'minute') !== false) {
                $seconds *= 60;
            }
        }
    }

    $hasCountdown = $seconds !== null && $seconds > 0;
    
    $icons = [
        'error'   => '<path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>',
        'warning' => '<path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>',
    ];
    $icon = $icons[$type] ?? $icons['error'];
@endphp

@if ($hasCountdown)
    {{-- Alert dengan Live Countdown --}}
    <div
        {{ $attributes->merge(['class' => 'auth-alert-' . $type . ' flex flex-col gap-3 p-3.5 rounded-lg border text-xs leading-relaxed font-medium']) }}
        role="alert"
        x-data="{
            seconds: {{ $seconds }},
            total: {{ $seconds }},
            finished: false,
            interval: null,
            
            init() {
                this.disableSubmitButton();
                this.startCountdown();
            },
            
            startCountdown() {
                this.interval = setInterval(() => {
                    if (this.seconds > 0) {
                        this.seconds--;
                    } else {
                        this.finish();
                    }
                }, 1000);
            },
            
            finish() {
                clearInterval(this.interval);
                this.finished = true;
                this.enableSubmitButton();
            },
            
            disableSubmitButton() {
                const btn = document.querySelector('{{ $submitButton }}');
                if (btn) {
                    btn.disabled = true;
                    btn.dataset.originalText = btn.textContent;
                    btn.classList.add('opacity-50', 'cursor-not-allowed');
                }
            },
            
            enableSubmitButton() {
                const btn = document.querySelector('{{ $submitButton }}');
                if (btn) {
                    btn.disabled = false;
                    btn.classList.remove('opacity-50', 'cursor-not-allowed');
                }
            },
            
            formatTime(sec) {
                if (sec >= 60) {
                    const m = Math.floor(sec / 60);
                    const s = sec % 60;
                    if (s > 0) {
                        return m + ' menit ' + s + ' detik';
                    }
                    return m + ' menit';
                }
                return sec + ' detik';
            },
            
            progressPercent() {
                return ((this.total - this.seconds) / this.total) * 100;
            }
        }"
        x-cloak
    >
        <div class="flex items-start gap-2.5">
            <svg class="w-4 h-4 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
                {!! $icon !!}
            </svg>
            
            <div class="flex-1 space-y-1.5">
                <p x-show="!finished">
                    <span x-text="'Terlalu banyak percobaan. Silakan coba lagi dalam '"></span>
                    <strong x-text="formatTime(seconds)" class="font-semibold"></strong>
                </p>
                <p x-show="finished" class="text-green-600 dark:text-green-400 font-medium">
                    ✓ Waktu tunggu telah berakhir. Silakan coba masuk kembali.
                </p>
            </div>
        </div>
        
        {{-- Progress Bar --}}
        <div x-show="!finished" class="w-full h-1 bg-black/10 dark:bg-white/10 rounded-full overflow-hidden">
            <div 
                class="h-full bg-current transition-all duration-1000 ease-linear"
                :style="'width: ' + progressPercent() + '%'"
            ></div>
        </div>
    </div>
@else
    {{-- Fallback: Alert biasa tanpa countdown --}}
    <div
        {{ $attributes->merge(['class' => 'auth-alert-' . $type . ' flex items-start gap-2.5 p-3.5 rounded-lg border text-xs leading-relaxed font-medium']) }}
        role="alert"
    >
        <svg class="w-4 h-4 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
            {!! $icon !!}
        </svg>
        <span>{{ $message ?: $slot }}</span>
    </div>
@endif
