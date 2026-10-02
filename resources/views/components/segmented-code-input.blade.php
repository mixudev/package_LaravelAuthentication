@props([
    'name' => 'code',
    'length' => 6,
    'charset' => 'numeric',
    'autocomplete' => 'off',
    'autofocus' => false,
    'label' => null,
    'activeWhen' => 'true',
    'sizeClass' => 'w-11 h-13',
])

@php
    $length = max(1, min(32, (int) $length));
    $isNumeric = $charset === 'numeric';
    $inputMode = $isNumeric ? 'numeric' : 'text';
    $pattern = $isNumeric ? '[0-9]*' : '[A-Za-z0-9]*';
    $labelId = $name . '-segmented-label';
    $errorId = $name . '-segmented-error';
@endphp

<div
    class="segmented-code-input space-y-2"
    x-data="{
        digits: Array({{ $length }}).fill(''),
        length: {{ $length }},
        numeric: @js($isNumeric),
        active: () => {!! $activeWhen !!},
        init() {
            @if ($autofocus)
            this.$nextTick(() => {
                if (this.active()) this.focusFirst();
            });
            @endif
        },
        clean(value) {
            const source = String(value || '');
            return (this.numeric ? source.replace(/[^0-9]/g, '') : source.toUpperCase().replace(/[^A-Z0-9]/g, ''))
                .slice(0, this.length);
        },
        sync() {
            const hidden = this.$refs.hidden;
            if (hidden) hidden.value = this.digits.join('');
        },
        focusFirst() {
            const first = this.$refs.box0;
            if (first) first.focus();
        },
        focusNext(index) {
            const next = this.$refs['box' + Math.min(index + 1, this.length - 1)];
            if (next) next.focus();
        },
        onInput(event, index) {
            const value = this.clean(event.target.value);
            this.digits[index] = value.slice(-1);
            event.target.value = this.digits[index];
            this.sync();
            if (this.digits[index] && index < this.length - 1) this.focusNext(index);
        },
        onPaste(event) {
            event.preventDefault();
            const value = this.clean((event.clipboardData || window.clipboardData).getData('text'));
            for (let index = 0; index < this.length; index++) this.digits[index] = value[index] || '';
            this.$nextTick(() => {
                for (let index = 0; index < this.length; index++) {
                    if (this.$refs['box' + index]) this.$refs['box' + index].value = this.digits[index];
                }
                this.sync();
                const target = this.$refs['box' + Math.min(value.length, this.length - 1)];
                if (target) target.focus();
            });
        },
        onKeydown(event, index) {
            if (event.key === 'Backspace') {
                if (this.digits[index]) {
                    this.digits[index] = '';
                    event.target.value = '';
                } else if (index > 0) {
                    this.digits[index - 1] = '';
                    const previous = this.$refs['box' + (index - 1)];
                    if (previous) { previous.value = ''; previous.focus(); }
                }
                this.sync();
                event.preventDefault();
            } else if (event.key === 'ArrowLeft' && index > 0) {
                this.$refs['box' + (index - 1)].focus();
                event.preventDefault();
            } else if (event.key === 'ArrowRight' && index < this.length - 1) {
                this.$refs['box' + (index + 1)].focus();
                event.preventDefault();
            }
        },
        reset() {
            this.digits.fill('');
            for (let index = 0; index < this.length; index++) {
                if (this.$refs['box' + index]) this.$refs['box' + index].value = '';
            }
            this.sync();
            if (this.active()) this.focusFirst();
        }
    }"
    x-effect="if (!active()) reset()"
>
    @if ($label)
        <p id="{{ $labelId }}" class="text-sm font-medium text-slate-700 dark:text-slate-200">{{ $label }}</p>
    @endif

    <input
        type="hidden"
        name="{{ $name }}"
        x-ref="hidden"
        value=""
        x-bind:disabled="!active()"
    >

    <div
        role="group"
        @if ($label) aria-labelledby="{{ $labelId }}" @endif
        class="flex items-center justify-center gap-2 sm:gap-2.5"
        @paste="onPaste($event)"
    >
        @for ($index = 0; $index < $length; $index++)
            @if (!$isNumeric && $index === 5)
                <span aria-hidden="true" class="text-slate-400 font-semibold">-</span>
            @endif
            <input
                type="text"
                maxlength="1"
                inputmode="{{ $inputMode }}"
                pattern="{{ $pattern }}"
                autocomplete="{{ $index === 0 ? $autocomplete : 'off' }}"
                @if (!$isNumeric) autocapitalize="characters" spellcheck="false" @endif
                x-ref="box{{ $index }}"
                aria-label="{{ ($label ?: ucfirst($name)) . ' ' . ($index + 1) . ' of ' . $length }}"
                aria-describedby="{{ $errorId }}"
                x-bind:disabled="!active()"
                @input="onInput($event, {{ $index }})"
                @keydown="onKeydown($event, {{ $index }})"
                @focus="$event.target.select()"
                class="auth-otp-input {{ $sizeClass }} text-center text-xl font-bold rounded-lg border outline-none transition duration-150 focus:scale-105 focus:ring-2 focus:ring-blue-500 disabled:opacity-50 motion-safe:focus:animate-pulse"
            >
        @endfor
    </div>

    <p id="{{ $errorId }}" role="alert" class="text-xs text-red-600" @if (!$errors->has($name)) hidden @endif>
        {{ $errors->first($name) }}
    </p>
</div>
