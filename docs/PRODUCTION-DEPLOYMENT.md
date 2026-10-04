# Production Deployment & Scaling Guide

**Package**: mixudev/laravel-authentication  
**Target Audience**: DevOps, SRE, Platform Engineers  
**Last Updated**: 2026-10-01

---

## Table of Contents

1. [Prerequisites](#prerequisites)
2. [Infrastructure Requirements](#infrastructure-requirements)
3. [Configuration Checklist](#configuration-checklist)
4. [Performance Tuning](#performance-tuning)
5. [High-Availability Setup](#high-availability-setup)
6. [Monitoring & Observability](#monitoring--observability)
7. [Scheduled Jobs](#scheduled-jobs)
8. [Kubernetes Deployment](#kubernetes-deployment)
9. [Security Hardening](#security-hardening)
10. [Troubleshooting](#troubleshooting)

---

## Prerequisites

### Minimum Requirements

**Single Server (up to 100 concurrent users)**:
- PHP 8.2+ with OPcache enabled
- MySQL 8.0+ or PostgreSQL 13+ or MariaDB 10.6+
- Redis 6.0+ (for cache + rate limiting)
- 2 CPU cores, 4GB RAM
- 20GB storage (SSD recommended)

**High-Traffic (1000+ concurrent users)**:
- Load balancer (Nginx, HAProxy, AWS ALB)
- 3+ application servers (horizontal scaling)
- Dedicated Redis cluster (cache + queue)
- Database read replicas (optional, for >10k users)
- CDN for static assets (optional)

---

## Infrastructure Requirements

### Database

**Schema Indexes**: Already optimized in migrations (v1.0.0+)
```sql
-- Verify indexes exist (run in production DB)
SHOW INDEX FROM authentication_attempts;
SHOW INDEX FROM authentication_account_lockouts;
SHOW INDEX FROM authentication_devices;

-- Expected indexes:
-- idx_attempts_id_time (identifier, attempted_at)
-- idx_attempts_ip_time (ip_address, attempted_at)
-- idx_lockouts_user_identifier (user_identifier)
-- idx_devices_user_fingerprint (user_id, device_fingerprint)
```

**Connection Pool** (for Laravel Octane / high concurrency):
```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=your_database
DB_USERNAME=your_user
DB_PASSWORD=your_password

# Connection pool (MySQL)
DB_POOL_MIN=5
DB_POOL_MAX=50
```

**Read/Write Splitting** (>10k active users):
```php
// config/database.php
'mysql' => [
    'write' => ['host' => '10.0.1.10'], // Master
    'read'  => [
        ['host' => '10.0.1.11'], // Replica 1
        ['host' => '10.0.1.12'], // Replica 2
    ],
    'sticky' => true, // Read from master after write
],
```

---

### Cache (Redis Recommended)

**Why Redis**:
- Rate limiter uses cache for counters (atomic increment)
- Session storage (faster than database)
- Queue backend (better than database)

**Configuration**:
```env
CACHE_DRIVER=redis
SESSION_DRIVER=redis
QUEUE_CONNECTION=redis

REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379
REDIS_DB=0  # Cache
REDIS_QUEUE_DB=1  # Queue (separate database)
```

**Redis Cluster** (>1000 req/sec):
```php
// config/database.php
'redis' => [
    'client' => 'phpredis',  // Faster than predis
    'cluster' => env('REDIS_CLUSTER', false),
    'clusters' => [
        'default' => [
            ['host' => '10.0.2.10', 'port' => 6379],
            ['host' => '10.0.2.11', 'port' => 6379],
            ['host' => '10.0.2.12', 'port' => 6379],
        ],
    ],
],
```

---

### Queue Workers

**Required only when queued email or asynchronous audit is enabled**:
```bash
# One worker handles the default queue and the package audit queue.
php artisan queue:work redis --queue=default,auth-audit --sleep=3 --tries=3 --max-time=3600

# Supervisor config (production)
[program:laravel-auth-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/html/artisan queue:work redis --queue=default,auth-audit --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=4
redirect_stderr=true
stdout_logfile=/var/www/html/storage/logs/worker.log
stopwaitsecs=3600
```

**Laravel Horizon** (recommended for monitoring):
```bash
composer require laravel/horizon
php artisan horizon:install
php artisan horizon
```

Monitor queue: `http://your-app.com/horizon`

---

## Configuration Checklist

### 1. Enable Queued Email (optional)
```php
// config/authentication.php
'mail' => [
    'queue' => false,  // default: synchronous delivery, no worker required
    'queue_connection' => null,  // Use default (redis recommended)
],
```

When enabled, the mailable runs on the application default queue, so any worker already consuming `default` delivers it. `php artisan queue:work` with no `--queue` flag is sufficient for email.

---

### 2. Enable Async Audit Logging (v1.9.0+ - High Traffic)

**For >1000 req/sec**: Offload audit writes to queue workers
```php
// config/authentication.php
'audit' => [
    'enabled'          => true,
    'driver'           => 'database',
    'queue'            => true,  // ✓ Enable async via queue
    'queue_connection' => null,  // null = use default (redis recommended)
    'queue_name'       => 'auth-audit',
    'queue_fallback'   => 'sync', // 'sync' = write inline if dispatch fails
    'retention_days'   => 90,
],
```

**Impact**:
- **Before**: 2 synchronous DB writes per login (~50-100ms latency)
- **After**: Async dispatch (~1-2ms), writes handled by queue workers
- **Scaling**: Horizontal (add more queue workers vs vertical DB scaling)

**Data safety when no worker is running**: `queue_fallback = 'sync'` means a failed
dispatch writes the audit record inline instead of dropping it. Audit data is
never lost when the worker is down; only the latency benefit is lost. Set
`queue_fallback = 'log'` to log-only instead of writing inline.

**Verify**:
```bash
php artisan queue:monitor auth-audit --max=200
# Should show: "auth-audit ....................... HEALTHY"
```

---

### 3. Rate Limiting Strategy

**IMPORTANT**: See [Rate Limiting Deployment Guide](./docs/security/rate-limiting-deployment.md) for comprehensive production requirements, fail mode policies, and troubleshooting.

**Composite (IP + Identifier)** — Best for most cases:
```php
'security' => [
    'rate_limits' => [
        'login' => [
            'enabled' => true,
            'max_attempts' => 5,
            'decay_minutes' => 1,
            'strategy' => 'composite',  // sha1(identifier + IP)
        ],
    ],
],
```

**IP-only** — Use if behind trusted proxy/CDN:
```php
'strategy' => 'ip',  // Rate limit per IP (ignores identifier)
```

**Identifier-only** — Use if IP unreliable (mobile users, NAT):
```php
'strategy' => 'identifier',  // Rate limit per email/username
```

#### Production Requirements for Rate Limiting

**Critical**:
1. **Redis Required**: File/array cache creates race conditions. Use Redis with atomic operations.
2. **Trusted Proxies**: Configure `TrustProxies` middleware with exact infrastructure IPs. Wrong config = all users appear as one IP or attackers spoof headers.
3. **Monitoring**: Alert on throttle rate >100/min, Redis down, cache latency >50ms.
4. **Fail Mode Policy**: Document per-feature behavior when cache unavailable (fail-safe vs fail-closed).

**Quick Setup**:
```env
CACHE_DRIVER=redis
REDIS_CACHE_DB=1  # Dedicated database for rate limits
```

**Verify Trusted Proxy**:
```php
// Test endpoint (remove after verification)
Route::get('/test-ip', fn(Request $request) => ['ip' => $request->ip()]);
// Should return real client IP, not load balancer IP
```

**Emergency Kill Switch**:
```bash
# Disable all rate limiting without deploy
redis-cli SET auth:emergency:disable_rate_limiting 1

# Clear rate limits for specific user
redis-cli --scan --pattern "auth_rl:login:*" | xargs redis-cli DEL
```

**See Full Guide**: [docs/security/rate-limiting-deployment.md](./docs/security/rate-limiting-deployment.md)
- Cache requirements & Redis configuration
- Trusted proxy setup per platform (AWS, Cloudflare, etc.)
- Fail mode policy per feature (login, OTP, password reset)
- Performance impact & scaling strategy
- Emergency procedures & rollback
- Production checklist (20 items)
- Troubleshooting common issues

---

### 4. Account Lockout

**Enable for security** (disabled by default):
```php
'security' => [
    'account_lockout' => [
        'enabled' => true,  // ← Enable this
        'max_failed_attempts' => 5,
        'lockout_duration_mins' => 15,
        'auto_unlock' => true,  // Unlock after duration expires
    ],
],
```

**Trade-off**:
- ✓ Prevents brute force
- ⚠ User must wait 15 min after 5 failures (support tickets may increase)

---

### 5. Session Configuration

**Database Driver** (multi-server):
```env
SESSION_DRIVER=database
SESSION_LIFETIME=120  # minutes
SESSION_SECURE_COOKIE=true  # HTTPS only
SESSION_SAME_SITE=lax
```

**Redis Driver** (high-performance):
```env
SESSION_DRIVER=redis
SESSION_CONNECTION=default
SESSION_LIFETIME=120
```

**File Driver** — NOT recommended for multi-server (sessions not shared).

---

### 6. HTTPS & Security Headers

**Force HTTPS** (production):
```php
// app/Providers/AppServiceProvider.php
public function boot()
{
    if ($this->app->environment('production')) {
        \URL::forceScheme('https');
    }
}
```

**Security Headers** (already included in package middleware):
```
X-Content-Type-Options: nosniff
X-Frame-Options: SAMEORIGIN
Referrer-Policy: strict-origin-when-cross-origin
```

**Additional Headers** (via web server):
```nginx
# nginx.conf
add_header Strict-Transport-Security "max-age=31536000; includeSubDomains" always;
add_header X-XSS-Protection "1; mode=block" always;
```

---

## Performance Tuning

### PHP OPcache (Production)

```ini
; php.ini
opcache.enable=1
opcache.memory_consumption=256
opcache.interned_strings_buffer=16
opcache.max_accelerated_files=20000
opcache.validate_timestamps=0  ; Disable in production (deploy = restart PHP-FPM)
opcache.revalidate_freq=0
opcache.fast_shutdown=1
```

**Restart PHP-FPM after deploy**:
```bash
sudo systemctl reload php8.2-fpm
```

---

### Database Query Optimization

**Verify Indexes in Production**:
```bash
php artisan tinker
>>> DB::connection()->enableQueryLog();
>>> // Perform login
>>> DB::getQueryLog();
// Check: queries should use indexes (EXPLAIN shows key != NULL)
```

**Slow Query Log** (MySQL):
```ini
[mysqld]
slow_query_log = 1
slow_query_log_file = /var/log/mysql/slow.log
long_query_time = 1  # Log queries >1 second
```

---

### Password Hashing (CPU Bottleneck)

**Bcrypt Rounds** (default: 12):
```env
BCRYPT_ROUNDS=12  # ~200ms per hash (intentional brute-force protection)
```

**Argon2id** (recommended, requires libsodium):
```env
HASH_DRIVER=argon2id
```

**Trade-off**:
- Lower rounds = faster login, weaker security
- Higher rounds = slower login, stronger security
- **Do NOT reduce below 10** (OWASP minimum)

**Horizontal Scaling** > Weakening Hash:
- Add more servers instead of reducing rounds
- Password hashing is designed to be slow

---

## High-Availability Setup

### Load Balancer Configuration

**Nginx (Layer 7)**:
```nginx
upstream laravel_app {
    least_conn;  # Route to least-busy server
    server 10.0.3.10:8000 max_fails=3 fail_timeout=30s;
    server 10.0.3.11:8000 max_fails=3 fail_timeout=30s;
    server 10.0.3.12:8000 max_fails=3 fail_timeout=30s;
}

server {
    listen 443 ssl http2;
    server_name auth.example.com;

    ssl_certificate /etc/ssl/certs/auth.example.com.crt;
    ssl_certificate_key /etc/ssl/private/auth.example.com.key;

    location / {
        proxy_pass http://laravel_app;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
    }
}
```

**Health Check Endpoint**:
```nginx
location /health {
    access_log off;
    return 200 "OK";
}
```

Or use package health check:
```bash
# In cron (every 1 min)
php artisan authentication:health || systemctl restart php8.2-fpm
```

---

### Session Affinity (Sticky Sessions)

**Why**: Avoid session loss during request distribution.

**AWS ALB**:
```hcl
# Terraform
resource "aws_lb_target_group" "app" {
  stickiness {
    type            = "lb_cookie"
    cookie_duration = 7200  # 2 hours
    enabled         = true
  }
}
```

**Nginx** (ip_hash):
```nginx
upstream laravel_app {
    ip_hash;  # Same IP → same server
    server 10.0.3.10:8000;
    server 10.0.3.11:8000;
}
```

**Better**: Use Redis session driver (no stickiness needed).

---

## Monitoring & Observability

### Health Checks

**Kubernetes Liveness Probe**:
```yaml
livenessProbe:
  exec:
    command: ["php", "artisan", "authentication:health"]
  initialDelaySeconds: 10
  periodSeconds: 30
  timeoutSeconds: 5
  failureThreshold: 3
```

**Readiness Probe**:
```yaml
readinessProbe:
  exec:
    command: ["php", "artisan", "authentication:health"]
  initialDelaySeconds: 5
  periodSeconds: 10
```

---

### Metrics to Monitor

**Application Metrics**:
- Login success rate (should be >95%)
- Login p95 latency (target: <500ms excluding password hash)
- Account lockout events (spike = potential attack)
- Queue depth (`default` queue, and `auth-audit` when async audit is on; target: <100 jobs)
- Failed authentication attempts per minute (baseline: <10/min)

**Infrastructure Metrics**:
- Redis memory usage (target: <80%)
- Database connection pool (target: <80% utilization)
- PHP-FPM active workers (target: <80%)
- Disk I/O (session table writes)

**Prometheus Exporter** (Laravel Exporter package):
```bash
composer require ensi/laravel-prometheus-exporter
php artisan vendor:publish --tag=prometheus-config
```

**Grafana Dashboard** (import template):
- Laravel Application Dashboard
- Redis Metrics
- MySQL Slow Queries

---

### Logging

**Structured Logging** (JSON):
```php
// config/logging.php
'stack' => [
    'driver' => 'stack',
    'channels' => ['daily', 'slack'],
    'formatter' => \Monolog\Formatter\JsonFormatter::class,
],
```

**Centralized Logging** (ELK Stack, Datadog, CloudWatch):
```bash
# Filebeat (ship logs to Elasticsearch)
filebeat.inputs:
  - type: log
    paths:
      - /var/www/html/storage/logs/*.log
    json.keys_under_root: true
```

**Alert on Anomalies**:
- Login failure rate >20% (attack)
- Account lockout spike >10 events/min (DDoS)
- Queue depth >500 jobs (worker down)

---

## Scheduled Jobs

**Production Crontab**:
```cron
# Run Laravel scheduler (manages all scheduled tasks)
* * * * * cd /var/www/html && php artisan schedule:run >> /dev/null 2>&1
```

**Add to Laravel Scheduler**:
```php
// app/Console/Kernel.php
protected function schedule(Schedule $schedule)
{
    // Prune old audit logs (keep 90 days)
    $schedule->command('authentication:prune')->dailyAt('02:00');

    // Prune expired sessions (keep 30 days)
    $schedule->command('authentication:prune-sessions')->dailyAt('03:00');

    // Health check every 5 minutes (optional, can alert via monitoring)
    $schedule->command('authentication:health')->everyFiveMinutes();
}
```

**Monitor Scheduled Jobs**:
```bash
# Laravel Telescope (dev/staging)
composer require laravel/telescope --dev
php artisan telescope:install

# Production: Use Horizon + external monitoring (Datadog, New Relic)
```

---

## Kubernetes Deployment

**Example Deployment** (`deployment.yaml`):
```yaml
apiVersion: apps/v1
kind: Deployment
metadata:
  name: laravel-auth-app
  namespace: production
spec:
  replicas: 3
  selector:
    matchLabels:
      app: laravel-auth
  template:
    metadata:
      labels:
        app: laravel-auth
    spec:
      containers:
      - name: app
        image: your-registry/laravel-app:v1.9.0
        ports:
        - containerPort: 8000
        env:
        - name: APP_ENV
          value: "production"
        - name: DB_HOST
          valueFrom:
            secretKeyRef:
              name: db-credentials
              key: host
        - name: REDIS_HOST
          value: "redis-cluster.default.svc.cluster.local"
        - name: QUEUE_CONNECTION
          value: "redis"
        livenessProbe:
          exec:
            command: ["php", "artisan", "authentication:health"]
          initialDelaySeconds: 10
          periodSeconds: 30
        readinessProbe:
          exec:
            command: ["php", "artisan", "authentication:health"]
          initialDelaySeconds: 5
          periodSeconds: 10
        resources:
          requests:
            memory: "256Mi"
            cpu: "250m"
          limits:
            memory: "512Mi"
            cpu: "500m"
---
apiVersion: apps/v1
kind: Deployment
metadata:
  name: laravel-auth-worker
spec:
  replicas: 2
  selector:
    matchLabels:
      app: laravel-worker
  template:
    spec:
      containers:
      - name: worker
        image: your-registry/laravel-app:v1.9.0
        command: ["php", "artisan", "queue:work", "redis", "--queue=default,auth-audit", "--tries=3", "--max-time=3600"]
        resources:
          requests:
            memory: "256Mi"
            cpu: "100m"
```

**Service** (`service.yaml`):
```yaml
apiVersion: v1
kind: Service
metadata:
  name: laravel-auth-svc
spec:
  selector:
    app: laravel-auth
  ports:
  - protocol: TCP
    port: 80
    targetPort: 8000
  type: ClusterIP
```

**Ingress** (HTTPS termination):
```yaml
apiVersion: networking.k8s.io/v1
kind: Ingress
metadata:
  name: laravel-auth-ingress
  annotations:
    cert-manager.io/cluster-issuer: "letsencrypt-prod"
spec:
  tls:
  - hosts:
    - auth.example.com
    secretName: auth-tls
  rules:
  - host: auth.example.com
    http:
      paths:
      - path: /
        pathType: Prefix
        backend:
          service:
            name: laravel-auth-svc
            port:
              number: 80
```

---

## Security Hardening

### 1. Rate Limiting Tuning

**Aggressive (high-security)**:
```php
'login' => [
    'max_attempts' => 3,  // Only 3 tries
    'decay_minutes' => 5, // 5-minute cooldown
],
```

**Relaxed (user-friendly)**:
```php
'login' => [
    'max_attempts' => 10,
    'decay_minutes' => 1,
],
```

---

### 2. IP Whitelist (Admin Access)

**Restrict login to known IPs** (corporate VPN):
```php
// routes/web.php
Route::middleware(['ip_whitelist'])->group(function() {
    Route::post('/login', [LoginController::class, 'login']);
});

// app/Http/Middleware/IpWhitelist.php
public function handle($request, Closure $next)
{
    $allowedIps = ['203.0.113.0/24', '198.51.100.5'];
    if (!in_array($request->ip(), $allowedIps)) {
        abort(403, 'Access denied from this IP');
    }
    return $next($request);
}
```

---

### 3. WAF (Web Application Firewall)

**Cloudflare** (managed WAF):
- Enable "Challenge" for login pages
- Bot Fight Mode (free tier)
- Rate limiting at edge (before hitting origin)

**AWS WAF**:
```hcl
resource "aws_wafv2_web_acl" "auth" {
  name  = "auth-waf"
  scope = "REGIONAL"

  default_action {
    allow {}
  }

  rule {
    name     = "RateLimit"
    priority = 1

    statement {
      rate_based_statement {
        limit              = 1000  # requests per 5 minutes
        aggregate_key_type = "IP"
      }
    }

    action {
      block {}
    }
  }
}
```

---

### 4. 2FA Enforcement (High-Risk Accounts)

**Require 2FA for admins**:
```php
// Middleware
if (auth()->user()->isAdmin() && !session('2fa_verified')) {
    return redirect()->route('authentication.two-factor.challenge');
}
```

**Prompt users to enable 2FA**:
```php
// After login
if (!$twoFactorService->isEnabledFor($user)) {
    session()->flash('warning', 'Enable 2FA for better security');
}
```

---

## Troubleshooting

### Queue Not Processing

**Symptom**: Emails not sent, `default` queue depth increasing.

**Check**:
```bash
php artisan queue:monitor default
# Shows: default ......................... [WARNING] (jobs: 345)
```

**Solution**:
```bash
# Restart queue worker
sudo supervisorctl restart laravel-auth-worker:*

# Or manually
php artisan queue:work redis --queue=default,auth-audit
```

---

### High Database CPU

**Symptom**: MySQL CPU >80%, slow queries.

**Check**:
```sql
SHOW FULL PROCESSLIST;
SHOW INDEX FROM authentication_attempts;
```

**Solution**:
- Verify indexes exist (`idx_attempts_id_time`, etc.)
- Run `php artisan authentication:prune` (delete old records)
- Enable read replicas (split read/write traffic)

---

### Redis Out of Memory

**Symptom**: `MISCONF Redis is configured to save RDB snapshots`

**Check**:
```bash
redis-cli INFO memory
# used_memory: 1.5G
# maxmemory: 1G  ← Problem
```

**Solution**:
```bash
# Increase Redis memory
redis-cli CONFIG SET maxmemory 4gb
redis-cli CONFIG SET maxmemory-policy allkeys-lru

# Or flush cache (temporary fix)
php artisan cache:clear
```

---

### Session Lost After Deploy

**Symptom**: Users logged out after deployment.

**Cause**: `SESSION_DRIVER=file` + multiple servers.

**Solution**: Use `redis` or `database` driver.

```env
SESSION_DRIVER=redis
```

---

## Summary

**Essential Steps for Production**:
1. ✅ Enable email queue (`mail.queue = true`)
2. ✅ Run queue worker (`php artisan queue:work`)
3. ✅ Use Redis for cache + session
4. ✅ Enable OPcache (`opcache.validate_timestamps=0`)
5. ✅ Schedule cleanup jobs (prune audit logs, sessions)
6. ✅ Set up monitoring (health checks, metrics, alerts)
7. ✅ Force HTTPS (`URL::forceScheme('https')`)
8. ✅ Configure rate limiting (composite strategy)
9. ✅ Enable account lockout (optional, security vs UX)
10. ✅ Test failover (kill one server, verify traffic routes)

**Performance Targets**:
- Login p50: <300ms (excluding password hash)
- Login p95: <500ms
- Email queue depth: <100 jobs
- Database connections: <80% pool
- Redis memory: <80% max

**Contact**:
- Issues: https://github.com/mixudev/laravel-authentication/issues
- Security: security@mixudev.com
