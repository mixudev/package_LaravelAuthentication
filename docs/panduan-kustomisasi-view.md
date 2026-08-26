# Panduan Lengkap Kustomisasi Tampilan (Custom Auth Views)

Package **`mixudev/laravel-authentication`** dirancang sangat fleksibel dan tidak mengikat developer ke tampilan tertentu. Anda memiliki kebebasan penuh untuk:
1. **Mengganti Template Layout Bawaan** (`split` 2-kolom atau `card` terpusat).
2. **Memodifikasi Komponen & View Bawaan** via `vendor:publish`.
3. **Membuat Tampilan Sendiri dari Nol (*Bring Your Own UI*)** dan mengarahkannya lewat `config/authentication.php` atau `.env`.

---

## 🚀 3 Cara Melakukan Kustomisasi Tampilan

---

### CARA 1: Ganti Template Layout Bawaan (Paling Mudah)

Package menyediakan 2 template siap pakai dengan estetika console dark mode:
- **`split` (Default)**: Tampilan 2-kolom (Panel branding & monitor telemetri di kiri, formulir di kanan).
- **`card`**: Tampilan 1-kolom kartu tengah minimalis dengan efek *ambient glow*.

Cukup ubah variabel di file `.env` Anda:

```env
# Pilihan: 'split' atau 'card'
AUTH_UI_LAYOUT=card

# Ubah informasi branding
AUTH_UI_BRAND_NAME="Aplikasi Saya"
AUTH_UI_BRAND_TAGLINE="Platform Keamanan Enterprise"
AUTH_UI_BRAND_BADGE="LIVE SECURE TLS 1.3"
```

Atau atur langsung di file [`config/authentication.php`](file:///d:/WEBSITE/PACKAGE/LaravelAuthentication/config/authentication.php):

```php
'ui' => [
    'layout'        => env('AUTH_UI_LAYOUT', 'card'),
    'theme'         => env('AUTH_UI_THEME', 'dark'),
    'brand_name'    => 'Portal Perusahaan',
    'brand_tagline' => 'Masuk untuk mengelola layanan',
],
```

---

### CARA 2: Publish View Bawaan & Edit Komponennya

Jika Anda menyukai struktur yang ada namun ingin mengubah teks, warna Tailwind, atau susunan komponen:

```bash
# Publish file view ke resources/views/vendor/authentication
php artisan vendor:publish --tag=authentication-views
```

Setelah dipublish, file akan berada di `resources/views/vendor/authentication/`:
```
resources/views/vendor/authentication/
├── components/
│   ├── layouts/
│   │   ├── auth.blade.php
│   │   ├── split.blade.php
│   │   └── card.blade.php
│   ├── input.blade.php
│   ├── button.blade.php
│   ├── checkbox.blade.php
│   ├── alert.blade.php
│   ├── otp-input.blade.php
│   ├── social-buttons.blade.php
│   └── brand-panel.blade.php
├── login.blade.php
├── register.blade.php
├── forgot-password.blade.php
├── reset-password.blade.php
├── otp-request.blade.php
└── otp-verify.blade.php
```
Laravel akan otomatis memprioritaskan view di folder `resources/views/vendor/authentication/` daripada file bawaan package.

---

### CARA 3: Buat Halaman Tampilan Sendiri dari Nol (*Bring Your Own UI*)

Jika Anda ingin membuat halaman login atau OTP sendiri (misalnya di `resources/views/auth/login.blade.php`), Anda **tidak perlu** mengubah controller atau alur backend package. Cukup daftarkan path view Anda di config!

#### 1. Atur di `.env` atau `config/authentication.php`:

```env
AUTH_VIEW_LOGIN=auth.login
AUTH_VIEW_REGISTER=auth.register
AUTH_VIEW_FORGOT_PASSWORD=auth.forgot-password
AUTH_VIEW_RESET_PASSWORD=auth.reset-password
AUTH_VIEW_OTP_REQUEST=auth.otp-request
AUTH_VIEW_OTP_VERIFY=auth.otp-verify
```

Pada [`config/authentication.php`](file:///d:/WEBSITE/PACKAGE/LaravelAuthentication/config/authentication.php):
```php
'views' => [
    'login'           => env('AUTH_VIEW_LOGIN', 'auth.login'),
    'register'        => env('AUTH_VIEW_REGISTER', 'auth.register'),
    'forgot_password' => env('AUTH_VIEW_FORGOT_PASSWORD', 'auth.forgot-password'),
    'reset_password'  => env('AUTH_VIEW_RESET_PASSWORD', 'auth.reset-password'),
    'otp_request'     => env('AUTH_VIEW_OTP_REQUEST', 'auth.otp-request'),
    'otp_verify'      => env('AUTH_VIEW_OTP_VERIFY', 'auth.otp-verify'),
    'otp_email'       => env('AUTH_VIEW_OTP_EMAIL', 'authentication::emails.otp'),
],
```

---

## 🛠️ Spesifikasi Teknis Pembuatan Halaman Kustom

Saat Anda membuat view sendiri, pastikan menggunakan **Target Action Form**, **Nama Input**, dan **Token CSRF** berikut:

---

### 1. Halaman Login Kustom (`login`)

* **URL View**: `/login` (Method `GET`)
* **Target Form**: `POST {{ route('login.perform') }}`
* **Input Wajib**:
  * `@csrf`
  * `name="identifier"` (bisa berisi email atau username)
  * `name="password"` (password akun)
  * `name="remember"` (opsional, checkbox nilai `1`)

#### Contoh File: `resources/views/auth/login.blade.php`

```blade
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Masuk — Portal Saya</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-100 flex items-center justify-center min-h-screen">

    <div class="max-w-md w-full bg-white p-8 rounded-xl shadow-md">
        <h2 class="text-2xl font-bold text-gray-900 mb-6 text-center">Masuk ke Akun</h2>

        {{-- Pesan Status Flash --}}
        @if (session('status'))
            <div class="mb-4 p-3 bg-green-100 text-green-700 text-sm rounded-lg">
                {{ session('status') }}
            </div>
        @endif

        {{-- Anda bisa memanfaatkan Blade Component dari package! --}}
        <x-authentication::social-buttons />

        <form method="POST" action="{{ route('login.perform') }}" class="space-y-4 mt-4">
            @csrf

            <div>
                <label class="block text-sm font-medium text-gray-700">Email atau Username</label>
                <input type="text" name="identifier" value="{{ old('identifier') }}" required autofocus
                    class="w-full mt-1 px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500 @error('identifier') border-red-500 @enderror">
                @error('identifier')
                    <span class="text-xs text-red-500">{{ $message }}</span>
                @enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700">Kata Sandi</label>
                <input type="password" name="password" required
                    class="w-full mt-1 px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                @error('password')
                    <span class="text-xs text-red-500">{{ $message }}</span>
                @enderror
            </div>

            <div class="flex items-center justify-between">
                <label class="flex items-center text-sm text-gray-600">
                    <input type="checkbox" name="remember" class="mr-2 rounded text-blue-600">
                    Ingat saya
                </label>
                @if (Route::has('password.request'))
                    <a href="{{ route('password.request') }}" class="text-sm text-blue-600 hover:underline">Lupa password?</a>
                @endif
            </div>

            <button type="submit" class="w-full py-2 px-4 bg-blue-600 hover:bg-blue-700 text-white font-semibold rounded-lg shadow">
                Masuk
            </button>
        </form>

        @if (Route::has('otp.request.form'))
            <div class="mt-4 text-center">
                <a href="{{ route('otp.request.form') }}" class="text-sm text-gray-500 hover:text-blue-600">Masuk tanpa password via OTP</a>
            </div>
        @endif
    </div>

</body>
</html>
```

---

### 2. Halaman Permintaan OTP Kustom (`otp-request`)

* **URL View**: `/otp/login` (Method `GET`)
* **Target Form**: `POST {{ route('otp.send') }}`
* **Input Wajib**:
  * `@csrf`
  * `name="identifier"` (Email atau username yang menerima kode OTP)

```blade
<form method="POST" action="{{ route('otp.send') }}">
    @csrf
    <input type="text" name="identifier" value="{{ old('identifier') }}" placeholder="nama@domain.com" required>
    @error('identifier') <span>{{ $message }}</span> @enderror
    
    <button type="submit">Kirim Kode OTP</button>
</form>
```

---

### 3. Halaman Verifikasi OTP Kustom (`otp-verify`)

* **URL View**: `/otp/verify` (Method `GET`)
* **Target Form**: `POST {{ route('otp.verify') }}`
* **Data yang Dikirim Controller ke View**:
  * `$identifier` (Alamat email/username target)
* **Input Wajib**:
  * `@csrf`
  * `name="identifier"` (hidden input, bernilai `$identifier`)
  * `name="code"` (kode OTP 6 digit)
  * `name="remember"` (opsional checkbox)

#### Contoh View OTP Verify Kustom:
```blade
<form method="POST" action="{{ route('otp.verify') }}">
    @csrf
    <input type="hidden" name="identifier" value="{{ $identifier }}">

    {{-- Anda bisa memakai Segmented OTP Component bawaan package yang sangat interaktif --}}
    <x-authentication::otp-input name="code" :length="6" />

    <button type="submit">Verifikasi & Masuk</button>
</form>

{{-- Form Kirim Ulang OTP --}}
<form method="POST" action="{{ route('otp.send') }}">
    @csrf
    <input type="hidden" name="identifier" value="{{ $identifier }}">
    <button type="submit">Kirim Ulang Kode</button>
</form>
```

---

### 4. Halaman Registrasi Kustom (`register`)

* **Target Form**: `POST {{ route('register.perform') }}`
* **Input Wajib**:
  * `@csrf`
  * `name="name"`
  * `name="email"`
  * `name="password"`
  * `name="password_confirmation"`

---

### 5. Halaman Lupa Password & Reset Password

* **Lupa Password**:
  * Target Form: `POST {{ route('password.email') }}`
  * Input: `name="email"`
* **Reset Password Baru**:
  * Target Form: `POST {{ route('password.update') }}`
  * Input:
    * `name="token"` (hidden input dengan nilai `$token`)
    * `name="email"` (input email terdaftar)
    * `name="password"` (password baru)
    * `name="password_confirmation"`

---

## 🧩 Menggunakan Komponen Modular di View Kustom Anda

Meskipun Anda membuat view sendiri, Anda tetap bisa memanfaatkan seluruh komponen Blade bawaan package:

```blade
{{-- Input Berstandar Keamanan (auto toggle password, auto @error) --}}
<x-authentication::input name="email" type="email" label="Alamat Email" :required="true" />

{{-- Tombol dengan Varian --}}
<x-authentication::button type="submit" variant="primary">Kirim</x-authentication::button>

{{-- Alert Notifikasi --}}
<x-authentication::alert type="success" message="Kata sandi berhasil diperbarui." />

{{-- Tombol Socialite Google/GitHub Otomatis --}}
<x-authentication::social-buttons />

{{-- Kotak 6-Digit OTP --}}
<x-authentication::otp-input name="code" :length="6" />
```

---

## 🔒 Praktik Keamanan Terbaik untuk View Kustom

1. **Selalu sertakan `@csrf`** di dalam setiap tag `<form>`.
2. **Jangan ubah error timing / message** pada alur *Forgot Password* untuk mencegah serangan *User Enumeration*.
3. **Gunakan atribut standar HTML**: `autocomplete="username"`, `autocomplete="current-password"`, `autocomplete="new-password"`.
4. **Gunakan escaping variabel** `{{ $data }}` dan hindari `{!! $data !!}` kecuali untuk konten SVG statis.
