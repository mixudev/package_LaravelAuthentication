# REST API Reference

All API routes are served under the configurable prefix (default: `/api/v1/auth`).

---

## 1. Login (Credentials)
**`POST /api/v1/auth/login`**

### Request:
```json
{
  "identifier": "user@example.com",
  "password": "SecurePassword123!",
  "strategy": "username_or_email",
  "remember": false
}
```

### Standard Response (200 OK):
```json
{
  "status": "success",
  "message": "Authenticated successfully.",
  "token": "1|abc123token...",
  "user": {
    "id": 1,
    "name": "Jane Doe",
    "email": "user@example.com"
  }
}
```

### 2FA Required Response (200 OK):
Returned when the account has Two-Factor Authentication enabled and the current device is not trusted:
```json
{
  "status": "two_factor_required",
  "message": "Two-factor authentication code required.",
  "pending_token": "a1b2c3d4e5f6...",
  "two_factor_required": true
}
```

---

## 2. Two-Factor Challenge Verification
**`POST /api/v1/auth/two-factor/verify`**

Verifies the TOTP 6-digit code or a single-use backup recovery code during login.

### Request:
```json
{
  "pending_token": "a1b2c3d4e5f6...",
  "code": "123456",
  "trust_device": true
}
```
*Note: `code` accepts either a 6-digit TOTP string (`"123456"`) or a recovery code (`"ABCD-1234"`).*

### Response (200 OK):
```json
{
  "message": "Two-factor authentication successful.",
  "token": "2|new_api_token...",
  "user": {
    "id": 1,
    "name": "Jane Doe",
    "email": "user@example.com"
  }
}
```

---

## 3. Two-Factor Authentication Setup (Authenticated)

### A. Get Secret & Recovery Codes
**`GET /api/v1/auth/two-factor/setup`**  
*Header: `Authorization: Bearer <token>`*

#### Response (200 OK):
```json
{
  "secret": "JBSWY3DPEHPK3PXP",
  "otpauth_url": "otpauth://totp/Laravel:user%40example.com?secret=JBSWY3DPEHPK3PXP&issuer=Laravel&algorithm=SHA1&digits=6&period=30",
  "recovery_codes": [
    "A1B2-C3D4",
    "E5F6-G7H8",
    "I9J0-K1L2",
    "M3N4-O5P6",
    "Q7R8-S9T0",
    "U1V2-W3X4",
    "Y5Z6-A7B8",
    "C9D0-E1F2"
  ]
}
```

### B. Confirm & Activate 2FA
**`POST /api/v1/auth/two-factor/confirm`**  
*Header: `Authorization: Bearer <token>`*

#### Request:
```json
{
  "code": "123456"
}
```

#### Response (200 OK):
```json
{
  "message": "Two-factor authentication enabled successfully."
}
```

### C. Disable 2FA
**`DELETE /api/v1/auth/two-factor/disable`**  
*Header: `Authorization: Bearer <token>`*

#### Request:
```json
{
  "password": "SecurePassword123!"
}
```

#### Response (200 OK):
```json
{
  "message": "Two-factor authentication disabled successfully."
}
```

---

## 4. Active Sessions & Device Management (Authenticated)

### A. List Active Sessions
**`GET /api/v1/auth/sessions`**  
*Header: `Authorization: Bearer <token>`*

#### Response (200 OK):
```json
{
  "sessions": [
    {
      "id": "1",
      "ip_address": "127.0.0.1",
      "user_agent": "Mozilla/5.0 (Windows NT 10.0; Win64; x64)...",
      "platform": "Windows 10/11",
      "browser": "Google Chrome",
      "device_name": "Google Chrome on Windows 10/11",
      "location": "Jakarta, ID",
      "last_activity": "2026-08-26T13:30:00.000000Z",
      "is_current_device": true
    }
  ]
}
```

### B. Revoke Specific Session
**`DELETE /api/v1/auth/sessions/{id}`**  
*Header: `Authorization: Bearer <token>`*

#### Response (200 OK):
```json
{
  "message": "Session revoked successfully."
}
```

### C. Revoke All Other Sessions
**`POST /api/v1/auth/sessions/revoke-others`**  
*Header: `Authorization: Bearer <token>`*

#### Request:
```json
{
  "password": "SecurePassword123!"
}
```

#### Response (200 OK):
```json
{
  "message": "All other sessions revoked successfully."
}
```

---

## 5. Confirm Password (Re-authentication for Sensitive API Actions)
**`POST /api/v1/auth/confirm-password`**  
*Header: `Authorization: Bearer <token>`*

### Request:
```json
{
  "password": "SecurePassword123!"
}
```

### Response (200 OK):
```json
{
  "message": "Password confirmed successfully.",
  "confirmed": true
}
```

---

## 6. User Registration
**`POST /api/v1/auth/register`**

### Request:
```json
{
  "name": "Jane Doe",
  "email": "user@example.com",
  "password": "SecurePassword123!",
  "password_confirmation": "SecurePassword123!"
}
```

### Response (201 Created):
```json
{
  "status": "success",
  "message": "Account registered successfully.",
  "token": "3|registration_token...",
  "user": {
    "id": 2,
    "name": "Jane Doe",
    "email": "user@example.com"
  }
}
```

---

## 7. Passwordless OTP Endpoints

### A. Send OTP Code
**`POST /api/v1/auth/otp/send`**

#### Request:
```json
{
  "identifier": "user@example.com"
}
```

#### Response (200 OK):
```json
{
  "status": "success",
  "message": "OTP code dispatched successfully."
}
```

### B. Verify OTP Code
**`POST /api/v1/auth/otp/verify`**

#### Request:
```json
{
  "identifier": "user@example.com",
  "code": "123456"
}
```

#### Response (200 OK):
```json
{
  "status": "success",
  "message": "OTP verified successfully.",
  "token": "4|otp_token...",
  "user": {
    "id": 1,
    "name": "Jane Doe",
    "email": "user@example.com"
  }
}
```

---

## 8. Password Recovery Endpoints

### A. Request Reset Link
**`POST /api/v1/auth/forgot-password`**

#### Request:
```json
{
  "email": "user@example.com"
}
```

#### Response (200 OK):
```json
{
  "status": "success",
  "message": "Password reset link sent to your email."
}
```

### B. Reset Password with Token
**`POST /api/v1/auth/reset-password`**

#### Request:
```json
{
  "email": "user@example.com",
  "token": "reset_token_from_email",
  "password": "NewSecurePassword123!",
  "password_confirmation": "NewSecurePassword123!"
}
```

#### Response (200 OK):
```json
{
  "status": "success",
  "message": "Password has been reset successfully."
}
```

---

## 9. Logout
**`POST /api/v1/auth/logout`**  
*Header: `Authorization: Bearer <token>`*

### Response (200 OK):
```json
{
  "status": "success",
  "message": "Logged out successfully."
}
```

---

## 10. Active Sessions & Device Management (Authenticated)

### A. List Active Sessions
**`GET /api/v1/auth/sessions`**  
*Header: `Authorization: Bearer <token>`*

#### Response (200 OK):
```json
{
  "status": "success",
  "sessions": [
    {
      "id": "sess_123456",
      "ip_address": "127.0.0.1",
      "user_agent": "Mozilla/5.0 (Windows NT 10.0; Win64; x64)...",
      "platform": "Windows 10/11",
      "browser": "Google Chrome",
      "device_name": "Google Chrome on Windows 10/11",
      "location": "Jakarta, ID",
      "last_activity": "2026-08-29T12:00:00.000000Z",
      "is_current_device": true
    }
  ]
}
```

### B. Revoke Specific Session
**`DELETE /api/v1/auth/sessions/{id}`**  
*Header: `Authorization: Bearer <token>`*

#### Response (200 OK):
```json
{
  "status": "success",
  "message": "Session revoked successfully."
}
```

### C. Revoke All Other Sessions
**`POST /api/v1/auth/sessions/revoke-others`**  
*Header: `Authorization: Bearer <token>`*

#### Request:
```json
{
  "password": "SecurePassword123!"
}
```

#### Response (200 OK):
```json
{
  "status": "success",
  "message": "All other sessions have been revoked."
}
```


---

# Lampiran: Sitemap Lengkap Rute

## A. Rute Web (Browser)

| Metode | URL | Nama Rute | Akses | Deskripsi |
| :--- | :--- | :--- | :---: | :--- |
| `GET` | `/login` | `login` | Guest | Halaman Form Login. |
| `POST` | `/login` | `login.perform` | Guest | Proses login (username/email + password). |
| `GET` | `/two-factor-challenge` | `two-factor.challenge` | Guest (Pending 2FA) | Input kode 6-digit TOTP / kode cadangan. |
| `POST` | `/two-factor-challenge` | `two-factor.verify` | Guest (Pending 2FA) | Verifikasi kode 2FA & selesaikan login. |
| `GET` | `/register` | `register` | Guest | Halaman Pendaftaran. |
| `POST` | `/register` | `register.perform` | Guest | Proses pembuatan akun. |
| `GET` | `/otp/login` | `otp.request.form` | Guest | Halaman minta kode OTP. |
| `POST` | `/otp/send` | `otp.send` | Guest | Kirim kode 6-digit OTP. |
| `GET` | `/otp/verify` | `otp.verify.form` | Guest | Halaman input kode OTP. |
| `POST` | `/otp/verify` | `otp.verify` | Guest | Validasi OTP & login. |
| `GET` | `/forgot-password` | `password.request` | Guest | Form minta link reset. |
| `POST` | `/forgot-password` | `password.email` | Guest | Kirim email link reset. |
| `GET` | `/reset-password/{token}` | `password.reset` | Guest | Form password baru. |
| `POST` | `/reset-password` | `password.update` | Guest | Simpan password baru. |
| `GET` | `/auth/{provider}/redirect` | `social.redirect` | Guest | Alihkan ke Google/GitHub. |
| `GET` | `/auth/{provider}/callback` | `social.callback` | Guest | Terima callback OAuth. |
| `POST` | `/logout` | `logout` | Auth | Keluar & hancurkan sesi. |
| `GET` | `/confirm-password` | `password.confirm` | Auth | Konfirmasi password. |
| `POST` | `/confirm-password` | `password.confirm.submit` | Auth | Validasi untuk aksi sensitif. |
| `GET` | `/auth/two-factor/setup` | `two-factor.setup` | Auth | Setup 2FA (QR & backup codes). |
| `POST` | `/auth/two-factor/confirm` | `two-factor.enable` | Auth | Aktifkan 2FA. |
| `DELETE` | `/auth/two-factor/disable` | `two-factor.disable` | Auth | Nonaktifkan 2FA. |
| `GET` | `/auth/sessions` | `auth.sessions.index` | Auth | Manajemen sesi & perangkat. |
| `DELETE` | `/auth/sessions/{id}` | `auth.sessions.destroy` | Auth | Cabut sesi tertentu. |
| `POST` | `/auth/sessions/revoke-others` | `auth.sessions.destroy-others` | Auth | Keluar dari semua perangkat lain. |
| `GET` | `/email/verify` | `verification.notice` | Auth | Notifikasi verifikasi email. |
| `GET` | `/email/verify/{id}/{hash}` | `verification.verify` | Auth | Validasi link verifikasi. |
| `POST` | `/email/verification-notification` | `verification.send` | Auth | Kirim ulang email verifikasi. |
| `GET` | `/auth/passkey/login-options` | `passkey.login.options` | Guest | Challenge options WebAuthn login. |
| `POST` | `/auth/passkey/login` | `passkey.login` | Guest | Validasi respon biometrik login. |
| `GET` | `/auth/passkey/register-options` | `passkey.register.options` | Auth | Challenge options registrasi. |
| `POST` | `/auth/passkey/register` | `passkey.register` | Auth | Simpan public key passkey. |
| `DELETE` | `/auth/passkey/{id}` | `passkey.destroy` | Auth | Hapus passkey terdaftar. |

## B. Endpoint REST API (`/api/v1/auth/*`)

| Metode | URL | Nama Rute | Auth | Deskripsi |
| :--- | :--- | :--- | :---: | :--- |
| `POST` | `/api/v1/auth/login` | `api.auth.login` | - | `{"identifier","password"}`. |
| `POST` | `/api/v1/auth/two-factor/verify` | `api.auth.two-factor.verify` | - | `{"user_id","code","trust_device"}`. |
| `POST` | `/api/v1/auth/register` | `api.auth.register` | - | `{"name","email","password","password_confirmation"}`. |
| `POST` | `/api/v1/auth/otp/send` | `api.auth.otp.send` | - | `{"identifier"}`. |
| `POST` | `/api/v1/auth/otp/verify` | `api.auth.otp.verify` | - | `{"identifier","code"}`. |
| `POST` | `/api/v1/auth/forgot-password` | `api.auth.password.email` | - | `{"email"}`. |
| `POST` | `/api/v1/auth/reset-password` | `api.auth.password.reset` | - | `{"email","token","password","password_confirmation"}`. |
| `POST` | `/api/v1/auth/social/{provider}` | `api.auth.social` | - | `{"token"}`. |
| `GET` | `/api/v1/auth/passkey/login-options` | `api.auth.passkey.login.options` | - | WebAuthn challenge options. |
| `POST` | `/api/v1/auth/passkey/login` | `api.auth.passkey.login` | - | Assertion response passkey. |
| `GET` | `/api/v1/auth/passkey/register-options` | `api.auth.passkey.register.options` | Bearer | Challenge registrasi. |
| `POST` | `/api/v1/auth/passkey/register` | `api.auth.passkey.register` | Bearer | Attestation response & `name`. |
| `DELETE` | `/api/v1/auth/passkey/{id}` | `api.auth.passkey.destroy` | Bearer | Hapus passkey. |
| `POST` | `/api/v1/auth/logout` | `api.auth.logout` | Bearer | Hapus token Sanctum. |
| `POST` | `/api/v1/auth/confirm-password` | `api.auth.password.confirm` | Bearer | `{"password"}`. |
| `GET` | `/api/v1/auth/two-factor/setup` | `api.auth.two-factor.setup` | Bearer | Secret, QR, backup codes. |
| `POST` | `/api/v1/auth/two-factor/confirm` | `api.auth.two-factor.confirm` | Bearer | `{"code"}`. |
| `DELETE` | `/api/v1/auth/two-factor/disable` | `api.auth.two-factor.disable` | Bearer | `{"password"}`. |
| `GET` | `/api/v1/auth/sessions` | `api.auth.sessions.index` | Bearer | List sesi aktif. |
| `DELETE` | `/api/v1/auth/sessions/{id}` | `api.auth.sessions.destroy` | Bearer | Hapus sesi spesifik. |
| `POST` | `/api/v1/auth/sessions/revoke-others` | `api.auth.sessions.destroy-others` | Bearer | `{"password"}`. |
