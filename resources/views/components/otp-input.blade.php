{{-- 
=============================================================================
KOMPONEN: SEGMENTED OTP INPUT (FULL AUTH SCOPED - AUTO ADVANCE)
Package: mixudev/laravel-authentication
Deskripsi: Input OTP bersegmen yang otomatis berpindah fokus saat mengetik.
           Menggunakan event handler murni tanpa x-model untuk menghindari konflik Alpine.
=============================================================================
--}}
@props([
    'name'      => 'code',
    'length'    => 6,
    'autofocus' => true,
])

{{-- 
    CATATAN TEKNIS:
    Kita TIDAK menggunakan x-model pada input individual karena Alpine.js akan memproses
    input event SETELAH kita melakukannya, sehingga e.target.value sudah di-reset ke
    nilai model lama. Solusinya: gunakan :value (binding satu arah) dan atur nilai secara
    manual melalui handler — ini memastikan auto-advance bekerja dengan benar.
--}}
<div 
    class="space-y-2"
    x-data="{
        digits: Array({{ $length }}).fill(''),
        length: {{ $length }},

        init() {
            @if ($autofocus)
            this.$nextTick(() => {
                const first = this.$refs['otp0'];
                if (first) first.focus();
            });
            @endif
        },

        /* Saat pengguna mengetik 1 karakter, pindah ke kotak berikutnya */
        onInput(e, idx) {
            const raw = e.target.value;
            /* Jika ada lebih dari 1 karakter (misal paste), tangani paste */
            if (raw.length > 1) {
                this.fillFromString(raw, idx);
                return;
            }
            /* Ambil hanya 1 karakter terakhir yang valid */
            const char = raw.replace(/[^a-zA-Z0-9]/g, '').slice(-1);
            this.digits[idx] = char;
            /* Paksa nilai DOM sesuai karakter bersih */
            e.target.value = char;
            this.syncHidden();
            /* Pindah fokus ke kotak berikutnya jika ada karakter */
            if (char && idx < this.length - 1) {
                this.$nextTick(() => {
                    const next = this.$refs['otp' + (idx + 1)];
                    if (next) next.focus();
                });
            }
        },

        /* Saat Backspace, hapus dan mundur satu kotak */
        onKeydown(e, idx) {
            if (e.key === 'Backspace') {
                if (this.digits[idx]) {
                    /* Kotak ini ada isinya: hapus isinya dulu */
                    this.digits[idx] = '';
                    e.target.value = '';
                    this.syncHidden();
                } else if (idx > 0) {
                    /* Kotak ini kosong: mundur ke kotak sebelumnya */
                    const prev = this.$refs['otp' + (idx - 1)];
                    if (prev) {
                        this.digits[idx - 1] = '';
                        prev.value = '';
                        prev.focus();
                        this.syncHidden();
                    }
                }
                e.preventDefault();
            }
        },

        /* Tangani tempel (paste) dari clipboard */
        onPaste(e) {
            e.preventDefault();
            const text = (e.clipboardData || window.clipboardData).getData('text').trim();
            this.fillFromString(text, 0);
        },

        /* Isi kotak-kotak dari string tempel/input panjang */
        fillFromString(str, startIdx) {
            const clean = str.replace(/[^a-zA-Z0-9]/g, '').slice(0, this.length);
            for (let i = 0; i < this.length; i++) {
                this.digits[i] = clean[i] || '';
                const el = this.$refs['otp' + i];
                if (el) el.value = clean[i] || '';
            }
            this.syncHidden();
            /* Fokuskan kotak setelah karakter terakhir yang diisi */
            const nextIdx = Math.min(clean.length, this.length - 1);
            this.$nextTick(() => {
                const target = this.$refs['otp' + nextIdx];
                if (target) target.focus();
            });
        },

        /* Sinkronisasi nilai ke input hidden yang akan dikirim ke server */
        syncHidden() {
            const hidden = document.getElementById('{{ $name }}-otp-hidden');
            if (hidden) hidden.value = this.digits.join('');
        }
    }"
>
    {{-- Input hidden yang menyimpan nilai OTP lengkap untuk dikirim form --}}
    <input 
        type="hidden" 
        id="{{ $name }}-otp-hidden"
        name="{{ $name }}" 
        value="{{ old($name, '') }}"
        required
    />

    {{-- Kotak-kotak OTP bersegmen --}}
    <div class="flex items-center justify-center gap-2 sm:gap-2.5" @paste="onPaste($event)">
        @for ($i = 0; $i < $length; $i++)
            <input 
                type="text"
                maxlength="1"
                inputmode="numeric"
                pattern="[0-9a-zA-Z]*"
                autocomplete="{{ $i === 0 ? 'one-time-code' : 'off' }}"
                x-ref="otp{{ $i }}"
                :value="digits[{{ $i }}]"
                @input="onInput($event, {{ $i }})"
                @keydown="onKeydown($event, {{ $i }})"
                @focus="$event.target.select()"
                class="auth-otp-input w-11 h-13 text-center text-xl font-bold rounded-lg border outline-none transition shadow-xs"
            />
        @endfor
    </div>

    {{-- Pesan error validasi --}}
    @if (isset($errors) && $errors->has($name))
        <p class="auth-field-error text-xs mt-1 text-center font-medium flex items-center justify-center gap-1">
            <svg class="w-3.5 h-3.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            <span>{{ $errors->first($name) }}</span>
        </p>
    @endif
</div>
