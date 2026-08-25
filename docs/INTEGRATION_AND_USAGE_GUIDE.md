# Panduan Lengkap Integrasi & Penggunaan Package (`mixudev/laravel-authentication`)

Dokumentasi ini menjelaskan secara menyeluruh cara memasang, mengonfigurasi, dan menggunakan package **`mixudev/laravel-authentication`** pada aplikasi Laravel baru maupun aplikasi Laravel yang sudah ada (*existing project*).

---

## Daftar Isi
1. [Instalasi Awal](#1-instalasi-awal)
2. [Penyesuaian Model & Database](#2-penyesuaian-model--database)
   - [A. Pada Project Baru (Fresh Laravel)](#a-pada-project-baru-fresh-laravel)
   - [B. Pada Project Lama (Existing Project)](#b-pada-project-lama-existing-project)
3. [Use Case 1: Web Login (Session & Blade UI)](#3-use-case-1-web-login-session--blade-ui)
4. [Use Case 2: API & Mobile / SPA (Token Bearer)](#4-use-case-2-api--mobile--spa-token-bearer)
5. [Use Case 3: Menggunakan Service Secara Manual di Controller Sendiri](#5-use-case-3-menggunakan-service-secara-manual-di-controller-sendiri)
6. [Use Case 4: Login Fleksibel (Email, Username, NIP / Employee ID)](#6-use-case-4-login-fleksibel-email-username-nip--employee-id)
7. [Use Case 5: Menangani Exception & Error Handling](#7-use-case-5-menangani-exception--error-handling)
8. [Use Case 6: Mendengarkan Event & Audit Trail](#8-use-case-6-mendengarkan-event--audit-trail)

---

## 1. Instalasi Awal

Jalankan perintah berikut di root folder project Laravel Anda:

```bash
# 1. Install package via Composer
composer require mixudev/laravel-authentication

# 2. Publish file konfigurasi package ke config/authentication.php
php artisan vendor:publish --tag=authentication-config

# 3. Publish file migrasi database bawaan package (Attempts, Histories, Password Histories)
php artisan vendor:publish --tag=authentication-migrations

# 4. Jalankan migrasi
php artisan migrate
```

---

## 2. Penyesuaian Model & Database

Buka file **`config/authentication.php`** yang sudah ter-publish di project Anda.

### A. Pada Project Baru (Fresh Laravel)
Model user default Laravel adalah `App\Models\User`. Pastikan config mengarah ke model tersebut:
```php
'user_model' => App\Models\User::class,
```

### B. Pada Project Lama (Existing Project)
Jika project lama Anda menggunakan model kustom (misal `App\Models\Admin`, `App\Models\Pegawai`, atau nama kolom berbeda):

```php
// config/authentication.php
return [
    'user_model' => App\Models\Pegawai::class,

    'login' => [
        'default_strategy' => 'username_or_email',

        // Sesuaikan nama kolom tabel di database Anda
        'identifiers' => [
            'username_column' => 'username',     // misal: 'nama_pengguna'
            'email_column'    => 'email',
            'custom_column'   => 'nip',           // misal kolom NIP
            'password_column' => 'password',
        ],
    ],
];
```

Pastikan Model Anda mengimplementasikan `Illuminate\Contracts\Auth\Authenticatable` (secara default trait `Illuminate\Foundation\Auth\User` sudah menyediakannya).

---

## 3. Use Case 1: Web Login (Session & Blade UI)

Secara default, Web Routes bawaan package **sudah langsung aktif**.

### Route Bawaan yang Tersedia:
| Method | URL | Nama Route | Fungsi |
| :--- | :--- | :--- | :--- |
| `GET` | `/login` | `login` | Menampilkan form login |
| `POST` | `/login` | `login.perform` | Memproses autentikasi |
| `POST` | `/logout` | `logout` | Logout & invalidasi session |
| `GET` | `/forgot-password`| `password.request`| Form lupa password |
| `POST` | `/forgot-password`| `password.email`  | Kirim link reset password |
| `GET` | `/reset-password/{token}` | `password.reset` | Form buat password baru |
| `POST` | `/reset-password` | `password.update` | Simpan password baru |

### Menyesuaikan Redirect URL Setelah Login & Logout
Tambahkan atau sesuaikan di `config/authentication.php`:
```php
'redirects' => [
    'login'  => '/dashboard', // Tujuan setelah berhasil login
    'logout' => '/login',     // Tujuan setelah logout
],
```

### Kustomisasi Tampilan Form Login (Blade)
Jika ingin menggunakan desain UI Blade milik Anda sendiri:
1. Publish views bawaan:
   ```bash
   php artisan vendor:publish --tag=authentication-views
   ```
2. File view akan muncul di `resources/views/vendor/authentication/login.blade.php`.
3. Anda bebas mendesain tampilannya dengan Bootstrap, Tailwind, atau CSS kustom. Contoh struktur form:

```html
<form method="POST" action="{{ route('login.perform') }}">
    @csrf

    <!-- Input Identifier (Bisa Email atau Username) -->
    <div>
        <label>Email atau Username</label>
        <input type="text" name="identifier" value="{{ old('identifier') }}" required autofocus>
        @error('identifier')
            <span class="error">{{ $message }}</span>
        @enderror
    </div>

    <!-- Input Password -->
    <div>
        <label>Password</label>
        <input type="password" name="password" required>
        @error('password')
            <span class="error">{{ $message }}</span>
        @enderror
    </div>

    <!-- Remember Me -->
    <div>
        <label>
            <input type="checkbox" name="remember" value="1"> Ingat Saya
        </label>
    </div>

    <button type="submit">Masuk</button>
</form>
```

---

## 4. Use Case 2: API & Mobile / SPA (Token Bearer)

Jika Anda membangun backend untuk Mobile App (Flutter/React Native) atau Frontend SPA (Vue/React/Next.js):

### 1. Aktifkan API Routes di `config/authentication.php`:
```php
'routes' => [
    'web' => [
        'enabled' => true,
    ],
    'api' => [
        'enabled'    => true, // Ubah menjadi true
        'prefix'     => 'api/v1/auth',
        'middleware' => ['api'],
    ],
],
```

### 2. Endpoint API yang Otomatis Aktif:
- `POST /api/v1/auth/login`
- `POST /api/v1/auth/logout` (Perlu Authorization header Bearer Token)
- `POST /api/v1/auth/forgot-password`
- `POST /api/v1/auth/reset-password`

### Contoh Request Login API:
```http
POST /api/v1/auth/login HTTP/1.1
Host: your-domain.com
Content-Type: application/json
Accept: application/json

{
    "identifier": "budi@example.com",
    "password": "PasswordRahasia123!"
}
```

### Contoh Response Sukses (200 OK):
```json
{
    "status": "success",
    "message": "Authenticated successfully.",
    "token": "1|q0w9e8r7t6y5u4i3o2p1...",
    "user": {
        "id": 1,
        "name": "Budi Santoso",
        "email": "budi@example.com"
    }
}
```

### Contoh Response Jika Kredensial Salah (401 Unauthorized):
```json
{
    "status": "invalid_credentials",
    "message": "Invalid credentials."
}
```

### Contoh Response Jika Terkena Rate Limiting / Terlalu Banyak Percobaan (429 Too Many Requests):
```json
{
    "status": "throttled",
    "message": "Too many login attempts. Please try again later.",
    "seconds_remaining": 54
}
```

---

## 5. Use Case 3: Menggunakan Service Secara Manual di Controller Sendiri

Jika Anda ingin menonaktifkan route bawaan dan membuat Controller custom sendiri:

1. Matikan routes di `config/authentication.php`:
   ```php
   'routes' => [
       'web' => ['enabled' => false],
       'api' => ['enabled' => false],
   ],
   ```

2. Buat Controller Anda sendiri dan panggil `LaravelAuthentication` Facade atau `AuthenticationServiceInterface`:

```php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Vendor\LaravelAuthentication\LaravelAuthentication;
use Vendor\LaravelAuthentication\DTO\LoginData;
use Vendor\LaravelAuthentication\DTO\AuthenticationContext;
use Vendor\LaravelAuthentication\Exceptions\InvalidCredentialsException;
use Vendor\LaravelAuthentication\Exceptions\AuthenticationThrottledException;
use Vendor\LaravelAuthentication\Exceptions\AccountLockedException;

class CustomAuthController extends Controller
{
    public function submitLogin(Request $request)
    {
        $request->validate([
            'login_id' => 'required|string',
            'password' => 'required|string',
        ]);

        $loginData = new LoginData(
            identifier: $request->input('login_id'),
            password: $request->input('password'),
            remember: $request->boolean('remember')
        );

        $context = AuthenticationContext::fromRequest($request);

        try {
            // Eksekusi pipeline autentikasi package
            $result = LaravelAuthentication::authenticate($loginData, $context);

            return redirect()->intended('/admin/home');

        } catch (AuthenticationThrottledException $e) {
            return back()->withErrors([
                'login_id' => "Terlalu banyak percobaan. Silakan coba lagi dalam {$e->secondsRemaining} detik."
            ]);

        } catch (AccountLockedException $e) {
            return back()->withErrors([
                'login_id' => "Akun Anda terkunci untuk sementara karena alasan keamanan."
            ]);

        } catch (InvalidCredentialsException $e) {
            return back()->withErrors([
                'login_id' => "Email/Username atau password yang Anda masukkan salah."
            ]);
        }
    }
}
```

---

## 6. Use Case 4: Login Fleksibel (Email, Username, NIP / Employee ID)

Package mendukung strategi login yang dapat dipilih atau diperluas:

### Pilihan Strategi Bawaan:
1. **`username_or_email` (Default)**: Otomatis mendeteksi jika format email maka mencari ke kolom email, jika bukan email maka mencari ke kolom username.
2. **`email_password`**: Hanya mengizinkan login via format email.
3. **`username_password`**: Hanya mengizinkan login via username.
4. **`custom_identifier`**: Mencari ke kolom khusus (misal `employee_id` atau `nip`).

### Contoh Menambahkan Strategi Login Kustom (Misal: Nomor HP / WhatsApp):

1. Buat class Strategy di `app/Authentication/PhonePasswordStrategy.php`:
```php
namespace App\Authentication;

use Illuminate\Contracts\Auth\Authenticatable;
use Vendor\LaravelAuthentication\Contracts\AuthenticationStrategyInterface;
use Vendor\LaravelAuthentication\DTO\LoginData;
use Vendor\LaravelAuthentication\DTO\AuthenticationContext;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class PhonePasswordStrategy implements AuthenticationStrategyInterface
{
    public function name(): string
    {
        return 'phone_password';
    }

    public function supports(LoginData $data): bool
    {
        // Mendukung input yang diawali dengan '08' atau '+62'
        return preg_match('/^(08|\+62)[0-9]+$/', trim($data->identifier)) === 1;
    }

    public function resolveUser(LoginData $data, AuthenticationContext $context): ?Authenticatable
    {
        $phone = preg_replace('/[^0-9]/', '', $data->identifier);
        return User::where('phone_number', $phone)->first();
    }

    public function validateCredentials(Authenticatable $user, LoginData $data): bool
    {
        return Hash::check($data->password, $user->getAuthPassword());
    }
}
```

2. Daftarkan di `AppServiceProvider::boot()`:
```php
use Vendor\LaravelAuthentication\Support\AuthenticationStrategyRegistry;
use App\Authentication\PhonePasswordStrategy;

public function boot(AuthenticationStrategyRegistry $registry): void
{
    $registry->register('phone_password', PhonePasswordStrategy::class);
}
```

---

## 7. Use Case 5: Menangani Exception & Error Handling

Package melempar typed exception eksplisit yang mempermudah penanganan:

| Exception Class | Kapan Terjadi | Solusi / HTTP Status |
| :--- | :--- | :--- |
| `InvalidCredentialsException` | Password salah atau user tidak ditemukan | `401 Unauthorized` / generic error message |
| `AuthenticationThrottledException` | Melebihi batas max attempts rate limiter | `429 Too Many Requests` (ada `$e->secondsRemaining`) |
| `AccountLockedException` | Akun terkunci karena percobaan gagal beruntun | `423 Locked` (ada `$e->lockoutMinutes`) |
| `AuthenticationConfigurationException` | File config rusak atau User Model salah | `500 Internal Server Error` (Fail-closed) |
| `InvalidStrategyException` | Strategi login tidak terdaftar di registry | `500 Internal Server Error` |

---

## 8. Use Case 6: Mendengarkan Event & Audit Trail

Package mendispatch event resmi Laravel pada setiap aktivitas autentikasi:

### Daftar Event Bawaan:
- `Vendor\LaravelAuthentication\Events\LoginAttempted`
- `Vendor\LaravelAuthentication\Events\LoginSucceeded`
- `Vendor\LaravelAuthentication\Events\LoginFailed`
- `Vendor\LaravelAuthentication\Events\LogoutPerformed`
- `Vendor\LaravelAuthentication\Events\AccountLocked`
- `Vendor\LaravelAuthentication\Events\PasswordChanged`

### Contoh Membuat Listener (Misal Notifikasi Login dari Device Baru):

1. Buat Listener di project Anda:
```bash
php artisan make:listener SendLoginNotificationListener
```

2. Isi logic listener:
```php
namespace App\Listeners;

use Vendor\LaravelAuthentication\Events\LoginSucceeded;
use Illuminate\Support\Facades\Log;

class SendLoginNotificationListener
{
    public function handle(LoginSucceeded $event): void
    {
        $user = $event->user;
        $ip = $event->context->ipAddress;
        $browser = $event->context->userAgent;

        // Kirim email atau Telegram notification ke pengguna
        Log::info("User {$user->getAuthIdentifier()} berhasil login dari IP {$ip} menggunakan browser {$browser}");
    }
}
```

3. Daftarkan di `EventServiceProvider` aplikasi Anda.
