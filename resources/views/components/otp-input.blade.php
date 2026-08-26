{{-- 
=============================================================================
KOMPONEN: SEGMENTED OTP INPUT (MODERN ZINC)
Package: mixudev/laravel-authentication
Deskripsi: Input 6 digit OTP bersegmen yang bersih, modern, dan responsif.
=============================================================================
--}}
@props([
    'name' => 'code',
    'length' => 6,
    'autofocus' => true,
])

<div 
    class="space-y-2"
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
    <input 
        type="hidden" 
        id="otp-hidden-input" 
        name="{{ $name }}" 
        value="{{ old($name, '') }}"
        required
    />

    <div class="flex items-center justify-center gap-2 sm:gap-2.5" @paste="handlePaste($event)">
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
                class="w-10 h-12 text-center text-lg font-semibold rounded-lg border border-zinc-300 dark:border-zinc-800 bg-white dark:bg-zinc-950 text-zinc-900 dark:text-zinc-100 focus:border-zinc-900 dark:focus:border-zinc-400 focus:ring-1 focus:ring-zinc-900 dark:focus:ring-zinc-400 shadow-xs outline-none transition"
            />
        @endfor
    </div>

    @if (isset($errors) && $errors->has($name))
        <p class="text-xs text-red-600 dark:text-red-400 mt-1 text-center font-medium">
            {{ $errors->first($name) }}
        </p>
    @endif
</div>
