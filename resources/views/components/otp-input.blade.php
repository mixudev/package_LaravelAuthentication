{{-- 
=============================================================================
KOMPONEN: SEGMENTED OTP INPUT
Package: mixudev/laravel-authentication
Deskripsi: Komponen input 6 digit OTP bersegmen dengan auto-focus otomatis ke digit berikutnya,
           dukungan paste lengkap (clipboard), serta sinkronisasi nilai ke hidden input form (Light & Dark).
=============================================================================
--}}
@props([
    'name' => 'code',
    'length' => 6,
    'autofocus' => true,
])

<div 
    class="space-y-3"
    x-data="{
        length: {{ $length }},
        digits: Array({{ $length }}).fill(''),
        init() {
            if ({{ $autofocus ? 'true' : 'false' }}) {
                this.$nextTick(() => {
                    const first = this.$refs.otp0;
                    if (first) first.focus();
                });
            }
        },
        handleInput(e, index) {
            const val = e.target.value;
            if (val.length > 1) {
                this.pasteCode(val);
                return;
            }
            this.digits[index] = val;
            this.syncHidden();
            if (val && index < this.length - 1) {
                const next = this.$refs['otp' + (index + 1)];
                if (next) next.focus();
            }
        },
        handleKeydown(e, index) {
            if (e.key === 'Backspace' && !this.digits[index] && index > 0) {
                const prev = this.$refs['otp' + (index - 1)];
                if (prev) {
                    prev.focus();
                    this.digits[index - 1] = '';
                    this.syncHidden();
                }
            }
        },
        handlePaste(e) {
            e.preventDefault();
            const pasteData = (e.clipboardData || window.clipboardData).getData('text').trim();
            this.pasteCode(pasteData);
        },
        pasteCode(str) {
            const clean = str.replace(/[^a-zA-Z0-9]/g, '').slice(0, this.length);
            for (let i = 0; i < this.length; i++) {
                this.digits[i] = clean[i] || '';
            }
            this.syncHidden();
            const nextIdx = Math.min(clean.length, this.length - 1);
            const target = this.$refs['otp' + nextIdx];
            if (target) target.focus();
        },
        syncHidden() {
            const hidden = document.getElementById('otp-hidden-input');
            if (hidden) {
                hidden.value = this.digits.join('');
            }
        }
    }"
>
    {{-- Hidden input yang dikirimkan bersama payload POST formulir --}}
    <input 
        type="hidden" 
        id="otp-hidden-input" 
        name="{{ $name }}" 
        value="{{ old($name, '') }}"
        required
    />

    {{-- Kotak Digit Terpisah --}}
    <div class="flex items-center justify-between gap-2 sm:gap-2.5" @paste="handlePaste($event)">
        @for ($i = 0; $i < $length; $i++)
            <input 
                type="text"
                maxlength="1"
                inputmode="numeric"
                pattern="[0-9a-zA-Z]*"
                autocomplete="one-time-code"
                x-ref="otp{{ $i }}"
                x-model="digits[{{ $i }}]"
                @input="handleInput($event, {{ $i }})"
                @keydown="handleKeydown($event, {{ $i }})"
                class="w-11 h-13 sm:w-12 sm:h-14 text-center text-lg sm:text-xl font-mono-code font-bold rounded-xl bg-white dark:bg-slate-900/90 border border-slate-300 dark:border-slate-800 text-slate-900 dark:text-white focus:border-amber-500 focus:ring-2 focus:ring-amber-500/20 hover:border-slate-400 dark:hover:border-slate-700 outline-none transition-all"
            />
        @endfor
    </div>

    {{-- Pesan Validasi Error Otomatis (@error) --}}
    @if (isset($errors) && $errors->has($name))
        <p class="text-xs text-rose-600 dark:text-rose-400 flex items-center gap-1 mt-1 font-medium">
            <svg class="w-3.5 h-3.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            <span>{{ $errors->first($name) }}</span>
        </p>
    @endif
</div>
