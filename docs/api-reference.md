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

