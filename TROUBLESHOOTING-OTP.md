# OTP Email Not Delivered — Troubleshooting Guide

## Symptom
User requests OTP code but email never arrives, even though API returns success message.

## Root Causes & Solutions

### 1. Queue Worker Not Running (MOST COMMON)

**Check Config:**
```php
// config/authentication.php
'mail' => [
    'queue' => true,  // ← If true, you MUST run queue:work
],
```

Queued OTP mail runs on the application **default** queue, so a plain
`php artisan queue:work` handles it. Only `authentication.audit.queue` uses a
named queue (`auth-audit`); if you enabled it, the worker needs
`--queue=default,auth-audit`.

**Diagnosis:**
```bash
# Check Laravel log
tail -f storage/logs/laravel.log

# Look for:
# "OTP email queued" ← Job created but not processed
```

**Fix:**
```bash
# Start queue worker in production/development
php artisan queue:work

# If asynchronous audit is also enabled:
php artisan queue:work --queue=default,auth-audit

# Or use Supervisor (recommended for production)
# See: https://laravel.com/docs/queues#supervisor-configuration
```

**Quick Test (Disable Queue Temporarily):**
```php
// config/authentication.php
'mail' => [
    'queue' => false,  // Force synchronous send for testing
],
```

---

### 2. SMTP Credentials Invalid

**Check .env:**
```env
MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=your-email@gmail.com
MAIL_PASSWORD=your-app-password  # NOT your Gmail password!
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@yourapp.com
MAIL_FROM_NAME="${APP_NAME}"
```

**Diagnosis:**
```bash
# Check log for error trace
tail -100 storage/logs/laravel.log | grep "OTP email dispatch failed"

# Common errors:
# - "530 5.7.0 Must issue a STARTTLS command first"  → Wrong port/encryption
# - "535 Authentication failed"                       → Invalid credentials
# - "Connection timeout"                              → Firewall/host blocked
```

**Test SMTP Manually:**
```bash
php artisan tinker
>>> Mail::raw('Test', fn($m) => $m->to('your-email@example.com')->subject('Test'));
```

---

### 3. Email Not Valid (Username Login)

**Behavior:**
- OTP only sends email when identifier IS an email address
- Username identifiers do not trigger email (by design, prevents spam)

**Check Log:**
```bash
tail -f storage/logs/laravel.log | grep "OTP email not sent"

# Will show:
# "OTP email not sent: invalid recipient"
# "identifier": "john_doe"  ← Username, not email
```

**Solution:**
- User must request OTP using their email address, not username
- Or implement custom email lookup from username in your app

---

### 4. Mail Driver = `log` (Development Mode)

**Check .env:**
```env
MAIL_MAILER=log  # ← Email written to log file, not sent
```

**Find Email Content:**
```bash
# Email appears in laravel.log as raw MIME text
tail -200 storage/logs/laravel.log | grep "MIME-Version"
```

**Fix for Real Sending:**
```env
MAIL_MAILER=smtp  # Or mailgun, ses, etc.
```

---

### 5. Rate Limiting (Too Many Requests)

**Diagnosis:**
```bash
# Check for throttle exception
tail -f storage/logs/laravel.log | grep "AuthenticationThrottledException"
```

**Config:**
```php
// config/authentication.php
'otp' => [
    'throttle_seconds' => 60,  // User must wait 60s between requests
],
```

**Reset Cache (Emergency):**
```bash
php artisan cache:clear
```

---

## Verification Checklist

Run these commands to verify OTP email system:

```bash
# 1. Check config
php artisan config:show authentication.mail
php artisan config:show authentication.features.otp

# 2. Test mail connectivity
php artisan tinker
>>> Mail::raw('Test', fn($m) => $m->to('YOUR-EMAIL@example.com')->subject('Test'));

# 3. Check queue status (if queue enabled)
php artisan queue:failed
php artisan queue:work --once  # Process one job manually

# 4. Watch logs in real-time
tail -f storage/logs/laravel.log

# 5. Request OTP via API/web form, then check log for:
#    - "OTP email queued" or "OTP email sent synchronously"
#    - Any "OTP email dispatch failed" errors
```

---

## Log Messages Reference

| Log Level | Message | Meaning |
|-----------|---------|---------|
| `INFO` | OTP email queued | Job created, waiting for queue:work |
| `INFO` | OTP email sent synchronously | Email sent immediately (queue=false) |
| `ERROR` | OTP email dispatch failed | SMTP/Mailable error (see trace) |
| `WARNING` | OTP email not sent: invalid recipient | Identifier is not valid email address |

---

## Production Recommendations

1. **Always Enable Queue:**
   ```php
   'mail' => ['queue' => true],
   ```

2. **Use Redis/Database Queue Driver:**
   ```env
   QUEUE_CONNECTION=redis  # NOT sync
   ```

3. **Run Queue Worker with Supervisor:**
   ```ini
   [program:laravel-worker]
   command=php /path/to/artisan queue:work --tries=3
   autostart=true
   autorestart=true
   ```

4. **Monitor Failed Jobs:**
   ```bash
   php artisan queue:failed
   php artisan queue:retry all  # Retry failed jobs
   ```

5. **Set Up Log Monitoring:**
   - Use Sentry, Bugsnag, or Laravel Telescope
   - Alert on "OTP email dispatch failed" errors

---

## Still Not Working?

1. Check `storage/logs/laravel.log` for detailed error trace
2. Verify firewall allows outbound SMTP connections (port 587/465)
3. Test with different email provider (Gmail, SendGrid, Mailgun)
4. Disable queue temporarily to isolate queue vs SMTP issue
5. Open GitHub issue with log excerpts (redact sensitive data)
