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
  "device_name": "iPhone 15 Pro"
}
```

### Response (200 OK):
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

---

## 2. Register
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
  "token": "2|registration_token...",
  "user": {
    "id": 2,
    "name": "Jane Doe",
    "email": "user@example.com"
  }
}
```

---

## 3. Request OTP Code
**`POST /api/v1/auth/otp/send`**

### Request:
```json
{
  "identifier": "user@example.com"
}
```

### Response (200 OK):
```json
{
  "status": "success",
  "message": "OTP code dispatched successfully."
}
```

---

## 4. Verify OTP Code
**`POST /api/v1/auth/otp/verify`**

### Request:
```json
{
  "identifier": "user@example.com",
  "code": "123456"
}
```

### Response (200 OK):
```json
{
  "status": "success",
  "message": "OTP verified successfully.",
  "token": "3|otp_token...",
  "user": {
    "id": 1,
    "name": "Jane Doe",
    "email": "user@example.com"
  }
}
```

---

## 5. Password Reset Request
**`POST /api/v1/auth/forgot-password`**

### Request:
```json
{
  "email": "user@example.com"
}
```

### Response (200 OK):
```json
{
  "status": "success",
  "message": "Password reset link has been dispatched if account exists."
}
```

---

## 6. Password Reset Submission
**`POST /api/v1/auth/reset-password`**

### Request:
```json
{
  "token": "xyz_reset_token",
  "email": "user@example.com",
  "password": "NewSecurePassword123!",
  "password_confirmation": "NewSecurePassword123!"
}
```

### Response (200 OK):
```json
{
  "status": "success",
  "message": "Password has been successfully updated."
}
```

---

## 7. Social OAuth Token Exchange
**`POST /api/v1/auth/social/{provider}`** *(e.g. `google`, `github`)*

### Response (200 OK):
```json
{
  "status": "success",
  "message": "Authenticated successfully with Google.",
  "token": "4|social_google_token...",
  "user": {
    "id": 1,
    "name": "Google User",
    "email": "user@gmail.com"
  }
}
```

---

## 8. Logout
**`POST /api/v1/auth/logout`** *(Requires Bearer Authorization)*

### Headers:
```
Authorization: Bearer 1|abc123token...
```

### Response (200 OK):
```json
{
  "status": "success",
  "message": "Logged out successfully."
}
```
