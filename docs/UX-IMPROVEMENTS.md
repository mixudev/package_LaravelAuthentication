# UX Improvements Guide

**Package**: mixudev/laravel-authentication v1.9.0+  
**Target**: Developers & UI/UX Designers  
**Last Updated**: 2026-10-01

---

## Overview

Package ini telah dilengkapi dengan **4 UX enhancements** kritis yang meningkatkan user experience secara signifikan, terutama saat terjadi throttle, lockout, atau error login:

1. ✅ **Live Countdown Timer** (throttle/lockout)
2. ✅ **Caps Lock Warning Indicator** (password field)
3. ✅ **Form Submit Loading State** (prevent double-submit)
4. ✅ **Auto-disable Submit Button** (selama countdown aktif)

---

## 1. Live Countdown Timer Alert

### Problem
User yang terkena rate limit atau account lockout tidak tahu **berapa lama harus menunggu**. Pesan error seperti *"Please try again in 45 seconds"* statis dan tidak membantu user memantau waktu tersisa.

### Solution
Komponen `<x-authentication::countdown-alert>` menampilkan:
- **Live countdown**: "Silakan coba lagi dalam **43 detik**" (update setiap detik)
- **Progress bar visual**: Bar yang mengecil smooth seiring waktu
- **Auto-disable submit button**: Tombol login disabled selama countdown
- **Success message**: Setelah countdown habis, pesan berubah: "Waktu tunggu telah berakhir. Silakan coba masuk kembali." + tombol auto-enabled

### Usage

**Otomatis aktif** di `login.blade.php` saat controller flash `auth_retry_after`:

```php
// Controller (sudah terintegrasi di LoginController.php)
} catch (AuthenticationThrottledException $e) {
    session()->flash('auth_retry_after', $e->secondsRemaining);
    throw ValidationException::withMessages([...]);
}
```

**Manual usage** di custom view:

```blade
{{-- Dengan retry_after eksplisit (integer detik) --}}
<x-authentication::countdown-alert 
    type="error" 
    :retryAfter="60"
    submitButton="#my-submit-btn"
/>

{{-- Auto-detect dari message text --}}
<x-authentication::countdown-alert 
    type="error" 
    message="Too many attempts. Please try again in 120 seconds."
/>
```

### Features
- **Multi-format**: Otomatis format waktu: `45 detik`, `2 menit 15 detik`, `5 menit`
- **Auto-parse**: Deteksi pola `in X seconds`, `dalam X detik`, `X menit` dari pesan error
- **Target button**: Specify CSS selector tombol yang di-disable (`#login-submit-btn`)
- **Graceful degradation**: Jika tidak ada `retryAfter`, fallback ke alert biasa

---

## 2. Caps Lock Warning Indicator

### Problem
User sering tidak sadar **Caps Lock aktif** saat mengetik password, menyebabkan:
- Gagal login berulang kali (salah password)
- Terkena rate limit / account lockout
- Frustasi user

### Solution
Field password otomatis mendeteksi Caps Lock aktif dan menampilkan badge peringatan:

```
🔒 Password: [**********]  👁️
    ⚠️ Caps Lock aktif
```

### Implementation
Sudah **terintegrasi otomatis** di `components/input.blade.php` untuk semua field `type="password"`:

```blade
<x-authentication::input 
    name="password"
    type="password"
    :label="__('authentication::messages.password_label')"
/>
```

### Features
- **Real-time detection**: Event listener `keydown` + `keyup` (Alpine.js)
- **Visual warning**: Amber badge dengan ikon warning
- **Non-intrusive**: Muncul di bawah field, tidak menggeser layout
- **Cross-browser**: Menggunakan `getModifierState('CapsLock')` (standar web API)

---

## 3. Form Submit Loading State

### Problem
User spam-click tombol submit saat form lambat (password hashing ~200ms):
- Multiple request dikirim
- Memperburuk rate limit counter
- Beban server meningkat
- User bingung (tidak ada feedback visual)

### Solution
Tombol submit otomatis:
- **Disabled** saat di-click pertama kali
- **Spinner animasi** muncul
- **Text berubah**: "Masuk" → "Memproses..."
- **Prevent double-submit**: Form hanya dikirim sekali

### Implementation
Sudah **terintegrasi otomatis** di `components/button.blade.php`:

```blade
<x-authentication::button id="login-submit-btn" type="submit" variant="primary">
    {{ __('authentication::messages.sign_in_btn') }}
</x-authentication::button>
```

### Features
- **Auto-detect submit**: Alpine.js `@click` listener
- **Spinner animation**: Tailwind `animate-spin`
- **Customizable text**: Prop `loadingText` (default: "Memproses...")
- **Manual control**: Prop `loading` untuk kontrol manual state

**Manual usage**:

```blade
<x-authentication::button 
    type="submit" 
    :loading="true"
    loadingText="Mengirim email..."
>
    Kirim OTP
</x-authentication::button>
```

---

## 4. Auto-disable Submit Button (Countdown Integration)

### Problem
Saat countdown aktif, user bisa tetap spam-click tombol submit:
- Request gagal (still throttled)
- Counter rate limit tidak reset
- User frustasi

### Solution
Komponen `countdown-alert` **otomatis disable tombol submit** via JavaScript:

```javascript
// Saat countdown dimulai
const btn = document.querySelector('#login-submit-btn');
btn.disabled = true;
btn.classList.add('opacity-50', 'cursor-not-allowed');

// Saat countdown habis
btn.disabled = false;
btn.classList.remove('opacity-50', 'cursor-not-allowed');
```

### Configuration
Specify target button via prop `submitButton`:

```blade
<x-authentication::countdown-alert 
    :retryAfter="session('auth_retry_after')"
    submitButton="#login-submit-btn"
/>
```

**Multiple forms** di satu halaman:

```blade
{{-- Form 1: Login --}}
<form id="login-form">
    <x-authentication::countdown-alert submitButton="#login-btn" />
    <button id="login-btn">Masuk</button>
</form>

{{-- Form 2: OTP --}}
<form id="otp-form">
    <x-authentication::countdown-alert submitButton="#otp-btn" />
    <button id="otp-btn">Verifikasi</button>
</form>
```

---

## Integration Points

### Throttle/Lockout Flow

```
1. User login gagal N kali
2. LoginController throw AuthenticationThrottledException
3. Controller flash: session()->flash('auth_retry_after', $seconds)
4. Redirect kembali ke login.blade.php
5. Blade detect session('auth_retry_after')
6. Render countdown-alert component
7. Countdown berjalan + disable button
8. Countdown habis → enable button + success message
```

### Controller Integration

**Already integrated** di:
- `LoginController::login()` (web)
- `TwoFactorChallengeController::verify()` (2FA)
- `OtpController::verify()` (OTP)
- `SessionController::destroyOthers()` (session revoke)

**Custom controller example**:

```php
use Vendor\LaravelAuthentication\Exceptions\AuthenticationThrottledException;

public function customLogin(Request $request)
{
    try {
        // ... authentication logic
    } catch (AuthenticationThrottledException $e) {
        session()->flash('auth_retry_after', $e->secondsRemaining);
        
        return back()->withErrors([
            'identifier' => "Too many attempts. Please try again in {$e->secondsRemaining} seconds."
        ]);
    }
}
```

---

## Browser Compatibility

| Feature                | Chrome | Firefox | Safari | Edge |
|------------------------|--------|---------|--------|------|
| Countdown Timer        | ✅ 90+  | ✅ 88+   | ✅ 14+  | ✅ 90+ |
| Caps Lock Detection    | ✅ 54+  | ✅ 52+   | ✅ 10.1+| ✅ 79+ |
| Form Loading State     | ✅ 90+  | ✅ 88+   | ✅ 14+  | ✅ 90+ |
| Alpine.js (required)   | ✅ 90+  | ✅ 88+   | ✅ 14+  | ✅ 90+ |

**Fallback**: Jika JavaScript disabled, form tetap berfungsi normal (progressive enhancement).

---

## Customization

### Custom Countdown Messages

Edit translation file `resources/lang/{locale}/messages.php`:

```php
// English
'throttle_countdown_prefix' => 'Too many attempts. Please try again in',
'throttle_countdown_success' => 'Wait time has ended. Please try logging in again.',

// Indonesian
'throttle_countdown_prefix' => 'Terlalu banyak percobaan. Silakan coba lagi dalam',
'throttle_countdown_success' => 'Waktu tunggu telah berakhir. Silakan coba masuk kembali.',
```

### Custom Styling

Override CSS classes via Tailwind config atau custom CSS:

```css
/* Countdown progress bar color */
.auth-alert-error .h-full {
    @apply bg-red-500;
}

/* Caps Lock warning color */
.text-amber-600 {
    color: #f59e0b; /* Customize color */
}

/* Loading spinner size */
.animate-spin {
    width: 1rem;
    height: 1rem;
}
```

---

## Testing

**Unit tests** tersedia di `tests/Unit/BladeComponentsTest.php`:

```bash
vendor/bin/phpunit tests/Unit/BladeComponentsTest.php
```

**Manual testing**:
1. Login gagal 5 kali → verify countdown muncul
2. Aktifkan Caps Lock → verify warning badge muncul
3. Submit form → verify spinner + disabled state

---

## Performance Impact

| Feature               | Impact      | Notes                          |
|-----------------------|-------------|--------------------------------|
| Countdown Timer       | ~2KB JS     | Alpine.js reactive (already loaded) |
| Caps Lock Detection   | Negligible  | Native browser API             |
| Loading State         | Negligible  | Alpine.js reactive             |
| **Total overhead**    | **~2KB**    | Minimal (gzip: ~800 bytes)     |

---

## Recommendations

### ✅ DO
- Keep `submitButton` ID consistent across forms
- Test countdown with real throttle/lockout scenario
- Verify Alpine.js loaded before using components

### ❌ DON'T
- Don't remove `session()->flash('auth_retry_after')` dari controller
- Don't override `x-data` Alpine scope di countdown-alert
- Don't disable JavaScript fallback

---

## Future Enhancements (Roadmap)

- [ ] Audio beep saat countdown habis (opt-in)
- [ ] Vibration API support (mobile)
- [ ] Toast notification alternative (non-intrusive)
- [ ] Keyboard shortcut: Enter to retry after countdown
- [ ] Progress circle variant (alternative to progress bar)

---

## Support

**Issues**: https://github.com/mixudev/laravel-authentication/issues  
**Docs**: https://github.com/mixudev/laravel-authentication/tree/main/docs  
**Examples**: Lihat `resources/views/login.blade.php` untuk implementasi lengkap
