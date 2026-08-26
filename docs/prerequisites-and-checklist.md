# Checklist Prasyarat & Peta Lengkap URL Sistem

Dokumen ini berisi daftar lengkap apa saja yang dibutuhkan oleh sistem autentikasi, layanan mana yang memerlukan pendaftaran kunci API pihak ketiga vs layanan mandiri (offline), cara mendapatkan kredensialnya, serta daftar seluruh URL (Web & REST API) yang tersedia.

---

## 1. Matriks Kebutuhan Layanan & Pendaftaran

| Fitur | Butuh Daftar API Luar? | Biaya | Di mana Mendaftarnya? | Tempat Meletakkan Kunci / Konfigurasi |
| :--- | :---: | :---: | :--- | :--- |
| **MFA / 2FA Authenticator (Google Auth/Authy)** | ❌ **TIDAK** | Gratis | *Tidak perlu daftar apapun. Berjalan 100% offline di server.* | `config/authentication.php` (`features.two_factor`) |
| **Manajemen Sesi & Perangkat (`/auth/sessions`)** | ❌ **TIDAK** | Gratis | *Tidak perlu daftar apapun. Deteksi built-in.* | `config/authentication.php` (`features.session_management`) |
| **Konfirmasi Password (`password.confirm`)** | ❌ **TIDAK** | Gratis | *Tidak perlu daftar apapun. Middleware built-in.* | `config/authentication.php` (`features.confirm_password`) |
| **Rate Limiting Granular** | ❌ **TIDAK** | Gratis | *Menggunakan cache bawaan Laravel (Redis/Database/File).* | `config/authentication.php` (`security.rate_limits`) |
| **CAPTCHA (Cloudflare Turnstile)** *(Rekomendasi)* | ✅ **YA** | 100% Gratis | [dash.cloudflare.com](https://dash.cloudflare.com) (Menu Turnstile) | `.env`: `AUTH_CAPTCHA_SITE_KEY`, `AUTH_CAPTCHA_SECRET_KEY` |
| **CAPTCHA (Google reCAPTCHA v2/v3)** | ✅ **YA** | Gratis | [google.com/recaptcha/admin](https://www.google.com/recaptcha/admin) | `.env`: `AUTH_CAPTCHA_SITE_KEY`, `AUTH_CAPTCHA_SECRET_KEY` |
| **Social Login (Google OAuth)** | ✅ **YA** | Gratis | [console.cloud.google.com](https://console.cloud.google.com/apis/credentials) | `.env`: `GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET` |
| **Social Login (GitHub OAuth)** | ✅ **YA** | Gratis | [github.com/settings/developers](https://github.com/settings/developers) | `.env`: `GITHUB_CLIENT_ID`, `GITHUB_CLIENT_SECRET` |
| **Pengiriman Email (OTP & Alert Device Baru)** | ✅ **YA** | Sesuai SMTP | Provider SMTP (Mailtrap, Brevo, Resend, Gmail SMTP, Postmark) | `.env`: `MAIL_MAILER`, `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD` |

---

## 2. Panduan Langkah Mendapatkan API Keys & Secret

### A. Cloudflare Turnstile CAPTCHA (Rekomendasi Utama)
1. Buka [dash.cloudflare.com](https://dash.cloudflare.com) dan login/daftar akun gratis.
2. Di sidebar navigasi sebelah kiri, pilih **Turnstile** -> klik **Add Site**.
3. Isi **Site Name** (misal: *Laravel Auth Local*), **Domain** (masukkan `localhost` untuk testing lokal), dan pilih widget mode **Managed**.
4. Klik **Create**. Salin **Site Key** dan **Secret Key**.
5. Tempelkan ke file `.env` aplikasi Anda:
   ```env
   AUTH_CAPTCHA_SITE_KEY=0x4AAAAAA...
   AUTH_CAPTCHA_SECRET_KEY=0x4AAAAAA...
   ```
6. Di `config/authentication.php`, pastikan:
   ```php
   'security' => [
       'captcha' => [
           'enabled'                       => true,
           'driver'                        => 'turnstile',
           'trigger_after_failed_attempts' => 3, // Adaptif: hanya minta captcha setelah 3x gagal login
       ],
   ],
   ```

---

### B. Google OAuth 2.0 (Login via Google)
1. Buka [Google Cloud Console](https://console.cloud.google.com/).
2. Buat Project baru -> Masuk ke **APIs & Services** > **Credentials**.
3. Klik **Create Credentials** -> pilih **OAuth Client ID** (tipe *Web Application*).
4. Di bagian **Authorized redirect URIs**, tambahkan:
   `http://localhost:8000/auth/google/callback` (sesuaikan dengan domain Anda saat live/staging).
5. Salin **Client ID** dan **Client Secret**, lalu masukkan ke file `.env`:
   ```env
   GOOGLE_CLIENT_ID=your-client-id.apps.googleusercontent.com
   GOOGLE_CLIENT_SECRET=GOCSPX-your-client-secret
   ```

---

### C. GitHub OAuth (Login via GitHub)
1. Buka [github.com/settings/developers](https://github.com/settings/developers) -> pilih **OAuth Apps** -> klik **New OAuth App**.
2. Masukkan **Application Name**, **Homepage URL** (`http://localhost:8000`), dan **Authorization callback URL**:
   `http://localhost:8000/auth/github/callback`
3. Klik **Register application** -> Klik **Generate a new client secret**.
4. Masukkan ke file `.env`:
   ```env
   GITHUB_CLIENT_ID=your-github-client-id
   GITHUB_CLIENT_SECRET=your-github-client-secret
   ```

---

### D. Konfigurasi SMTP Email (Untuk Pengiriman Kode OTP & Email Alert)
Masukkan kredensial SMTP di `.env` (contoh menggunakan Mailtrap untuk testing lokal):
```env
MAIL_MAILER=smtp
MAIL_HOST=sandbox.smtp.mailtrap.io
MAIL_PORT=2525
MAIL_USERNAME=your-username
MAIL_PASSWORD=your-password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS="no-reply@example.com"
MAIL_FROM_NAME="${APP_NAME}"
```

Jika ingin mengaktifkan pengiriman email di latar belakang (*queue*):
```php
// config/authentication.php
'mail' => [
    'queue' => true,
    'queue_name' => 'auth-emails',
],
```
Lalu jalankan worker di terminal: `php artisan queue:work --queue=auth-emails`.

---

## 3. Peta Lengkap Rute & URL Sistem (Sitemap)

Berikut adalah daftar seluruh rute Web dan endpoint REST API yang disediakan oleh package:

### 🌐 A. Rute Web (Tampilan Antarmuka / Browser)

| Metode HTTP | URL Path | Nama Rute (Route Name) | Hak Akses | Deskripsi & Kegunaan |
| :--- | :--- | :--- | :---: | :--- |
| `GET` | `/login` | `login` | Guest | Halaman Form Login Kredensial. |
| `POST` | `/login` | `login.perform` | Guest | Memproses login kredensial (username/email + password). |
| `GET` | `/two-factor-challenge` | `two-factor.challenge` | Guest (Pending 2FA) | Halaman input kode 6-digit TOTP / kode cadangan saat login. |
| `POST` | `/two-factor-challenge` | `two-factor.verify` | Guest (Pending 2FA) | Memverifikasi kode 2FA dan menyelesaikan sesi login. |
| `GET` | `/register` | `register` | Guest | Halaman Pendaftaran Akun Baru. |
| `POST` | `/register` | `register.perform` | Guest | Memproses pembuatan akun baru. |
| `GET` | `/otp/login` | `otp.request.form` | Guest | Halaman Permintaan Kode Masuk Tanpa Password (OTP). |
| `POST` | `/otp/send` | `otp.send` | Guest | Mengirimkan kode 6-digit OTP ke email user. |
| `GET` | `/otp/verify` | `otp.verify.form` | Guest | Halaman Input Verifikasi Kode OTP. |
| `POST` | `/otp/verify` | `otp.verify` | Guest | Memvalidasi kode OTP dan langsung login. |
| `GET` | `/forgot-password` | `password.request` | Guest | Halaman Form Permintaan Link Reset Password. |
| `POST` | `/forgot-password` | `password.email` | Guest | Mengirimkan email link reset password. |
| `GET` | `/reset-password/{token}` | `password.reset` | Guest | Halaman Form Input Password Baru. |
| `POST` | `/reset-password` | `password.update` | Guest | Menyimpan kata sandi baru. |
| `GET` | `/auth/{provider}/redirect` | `social.redirect` | Guest | Mengalihkan user ke halaman login Google / GitHub. |
| `GET` | `/auth/{provider}/callback` | `social.callback` | Guest | Menerima callback OAuth dan login user. |
| `POST` | `/logout` | `logout` | Auth | Keluar dari sistem & menghancurkan sesi aktif. |
| `GET` | `/confirm-password` | `password.confirm` | Auth | Halaman Konfirmasi Password sebelum aksi sensitif. |
| `POST` | `/confirm-password` | `password.confirm.submit` | Auth | Memvalidasi password untuk membuka kunci aksi sensitif. |
| `GET` | `/auth/two-factor/setup` | `two-factor.setup` | Auth | Halaman Setup 2FA (Scan QR Code & Backup Codes). |
| `POST` | `/auth/two-factor/confirm` | `two-factor.enable` | Auth | Mengaktifkan 2FA setelah verifikasi kode pertama. |
| `DELETE`| `/auth/two-factor/disable` | `two-factor.disable` | Auth | Menonaktifkan 2FA dengan konfirmasi password. |
| `GET` | `/auth/sessions` | `auth.sessions.index` | Auth | Halaman Manajemen Sesi & Perangkat Aktif. |
| `DELETE`| `/auth/sessions/{id}` | `auth.sessions.destroy` | Auth | Mencabut sesi perangkat tertentu. |
| `POST` | `/auth/sessions/revoke-others`| `auth.sessions.destroy-others`| Auth | Mengeluarkan semua sesi di perangkat lain. |
| `GET` | `/email/verify` | `verification.notice` | Auth | Halaman Pemberitahuan Verifikasi Email. |
| `GET` | `/email/verify/{id}/{hash}` | `verification.verify` | Auth | Memvalidasi link verifikasi email bertanda tangan. |
| `POST` | `/email/verification-notification` | `verification.send` | Auth | Mengirim ulang email verifikasi. |

---

### 🔌 B. Endpoint REST API (`/api/v1/auth/*`)

| Metode HTTP | URL Path | Nama Rute | Header Wajib | Deskripsi Payload |
| :--- | :--- | :--- | :---: | :--- |
| `POST` | `/api/v1/auth/login` | `api.auth.login` | - | Body: `{"identifier", "password"}`. |
| `POST` | `/api/v1/auth/two-factor/verify` | `api.auth.two-factor.verify` | - | Body: `{"user_id", "code", "trust_device"}`. |
| `POST` | `/api/v1/auth/register` | `api.auth.register` | - | Body: `{"name", "email", "password", "password_confirmation"}`. |
| `POST` | `/api/v1/auth/otp/send` | `api.auth.otp.send` | - | Body: `{"identifier"}`. |
| `POST` | `/api/v1/auth/otp/verify` | `api.auth.otp.verify` | - | Body: `{"identifier", "code"}`. |
| `POST` | `/api/v1/auth/forgot-password` | `api.auth.password.email` | - | Body: `{"email"}`. |
| `POST` | `/api/v1/auth/reset-password` | `api.auth.password.reset` | - | Body: `{"email", "token", "password", "password_confirmation"}`. |
| `POST` | `/api/v1/auth/social/{provider}` | `api.auth.social` | - | Body: `{"token"}`. |
| `POST` | `/api/v1/auth/logout` | `api.auth.logout` | `Authorization: Bearer <token>` | Menghapus token Sanctum aktif. |
| `POST` | `/api/v1/auth/confirm-password` | `api.auth.password.confirm` | `Authorization: Bearer <token>` | Body: `{"password"}`. |
| `GET` | `/api/v1/auth/two-factor/setup` | `api.auth.two-factor.setup` | `Authorization: Bearer <token>` | Mendapatkan Secret Key, QR Code URL, dan Backup Codes. |
| `POST` | `/api/v1/auth/two-factor/confirm` | `api.auth.two-factor.confirm` | `Authorization: Bearer <token>` | Body: `{"code"}`. |
| `DELETE`| `/api/v1/auth/two-factor/disable` | `api.auth.two-factor.disable` | `Authorization: Bearer <token>` | Body: `{"password"}`. |
| `GET` | `/api/v1/auth/sessions` | `api.auth.sessions.index` | `Authorization: Bearer <token>` | Mengambil array JSON seluruh sesi perangkat aktif. |
| `DELETE`| `/api/v1/auth/sessions/{id}` | `api.auth.sessions.destroy` | `Authorization: Bearer <token>` | Menghapus sesi spesifik berdasarkan ID. |
| `POST` | `/api/v1/auth/sessions/revoke-others`| `api.auth.sessions.destroy-others`| `Authorization: Bearer <token>` | Body: `{"password"}`. |
