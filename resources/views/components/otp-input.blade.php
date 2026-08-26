{{-- 
=============================================================================
KOMPONEN: SEGMENTED OTP INPUT (CLEAN BREEZE STYLE)
Package: mixudev/laravel-authentication
Deskripsi: Input 6 digit OTP bersegmen yang bersih dan mudah digunakan.
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

    <div class="flex items-center justify-center gap-2" @paste="handlePaste($event)">
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
                class="w-10 h-12 text-center text-lg font-semibold rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 shadow-sm outline-none transition"
            />
        @endfor
    </div>

    @if (isset($errors) && $errors->has($name))
        <p class="text-sm text-red-600 dark:text-red-400 mt-1 text-center">
            {{ $errors->first($name) }}
        </p>
    @endif
</div>
