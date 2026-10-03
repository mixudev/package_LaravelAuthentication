# Rate Limiting Production Deployment Guide

**Package**: mixudev/laravel-authentication  
**Target**: DevOps, SRE, Platform Engineers  
**Version**: 1.10.0+  
**Last Updated**: 2026-10-02

---

## Overview

This guide covers production deployment of the enterprise-grade multi-layer rate limiting system. Rate limiting protects authentication endpoints from credential stuffing, brute force, account enumeration, and distributed abuse attacks.

**Critical Requirements**:
- Redis or equivalent atomic cache store (mandatory for multi-worker deployments)
- Trusted proxy configuration (CDN/load balancer)
- Monitoring and alerting infrastructure
- Documented fail mode policy per feature

---

## Table of Contents

1. [Cache Requirements](#cache-requirements)
2. [Trusted Proxy Configuration](#trusted-proxy-configuration)
3. [Fail Mode Policy](#fail-mode-policy)
4. [Performance Impact](#performance-impact)
5. [Rate Limit Strategies](#rate-limit-strategies)
6. [Emergency Controls](#emergency-controls)
7. [Monitoring & Alerts](#monitoring--alerts)
8. [Production Checklist](#production-checklist)
9. [Rollback Procedures](#rollback-procedures)
10. [Troubleshooting](#troubleshooting)

---

## Cache Requirements

### Why Redis Is Mandatory

Rate limiting counters use **atomic increment operations** (`INCR`). Non-atomic operations create race conditions:

```php
// WRONG (race condition - allows unlimited attempts)
$count = Cache::get('key', 0);
if ($count < 5) {
    Cache::put('key', $count + 1);  // Two requests read 4, both write 5
    // Attack proceeds
}

// CORRECT (atomic - Laravel RateLimiter uses this)
$count = Cache::increment('key');  // Redis INCR is atomic
if ($count > 5) {
    // Block
}
```

**Impact of Non-Atomic Store**:
- File cache: atomic `add()` uses an exclusive file lock; suitable for one shared filesystem, but not a substitute for a shared distributed cache across nodes
- Array cache: `Repository::add()` falls back to `get()` + `put()`; suitable only for single-process tests, never multi-worker or multi-server production
- Database cache: the installed Laravel `DatabaseStore` pre-reads before `insertOrIgnore()`; do not rely on it for cross-process single-use gates or counters

### Single-Use Security Requirement

OTP codes, pending 2FA tokens, passkey registration challenges, and their consumed markers require an atomic `add()` gate. The package source was checked against the installed Laravel cache stores: Redis uses an atomic Lua-backed operation, Memcached uses its native add, and FileStore uses an exclusive lock. These are the production-supported options for the single-use guarantee. ArrayStore and DatabaseStore do not provide that guarantee under concurrent workers; they remain available for backward compatibility, but deployment on those stores must be treated as a security configuration failure for multi-worker or multi-server authentication.

The package does not silently force Redis. Hosts may use Memcached or a shared FileStore where its operational model is appropriate. A host that selects ArrayStore or DatabaseStore must either run one process with no concurrent authentication workers or switch to Redis/Memcached before claiming single-use protection. The property tests in `tests/Concurrency/AtomicClaimGatePropertyTest.php` run on ArrayStore and prove logical invariants only; they are not evidence of true parallel atomicity.

### Redis Configuration

**Minimum Redis Version**: 6.0+

**Production Setup**:
```env
CACHE_DRIVER=redis
SESSION_DRIVER=redis
QUEUE_CONNECTION=redis

REDIS_HOST=127.0.0.1
REDIS_PASSWORD=your_strong_password
REDIS_PORT=6379

# Separate databases for isolation
REDIS_DB=0           # Application cache
REDIS_CACHE_DB=1     # Rate limit counters (dedicated)
REDIS_SESSION_DB=2   # Sessions
REDIS_QUEUE_DB=3     # Queue jobs
```

**Cache Connection** (`config/cache.php`):
```php
'stores' => [
    'redis' => [
        'driver' => 'redis',
        'connection' => 'cache',  // Uses REDIS_CACHE_DB
    ],
],
```

**Redis Persistence** (for counter durability):
```conf
# redis.conf
save 900 1        # Save if 1 key changed in 15 min
save 300 10       # Save if 10 keys changed in 5 min
save 60 10000     # Save if 10k keys changed in 1 min

# AOF (append-only file) for better durability
appendonly yes
appendfsync everysec
```

### Redis Cluster (High Availability)

For >1000 req/sec or multi-region:

```php
// config/database.php
'redis' => [
    'client' => 'phpredis',  // Faster than predis
    'cluster' => true,
    'clusters' => [
        'default' => [
            ['host' => '10.0.2.10', 'port' => 6379],
            ['host' => '10.0.2.11', 'port' => 6379],
            ['host' => '10.0.2.12', 'port' => 6379],
        ],
    ],
    'options' => [
        'cluster' => 'redis',
    ],
],
```

### Key Namespace Isolation

All rate limit keys use the prefix `auth_rl:` to prevent collisions:

```
auth_rl:login:comp:sha256(identifier|ip)
auth_rl:otp_request:id:sha256(identifier)
auth_rl:registration:ip:203.0.113.5
auth_rl:two_factor:comp:sha256(user@example.com|198.51.100.10)
```

**Cache Flush Safety**: Standard `php artisan cache:clear` clears rate limit state. Use Redis database isolation or targeted flush:

```bash
# Flush only application cache (preserves rate limits if on separate DB)
php artisan cache:forget 'app:*'

# Emergency: Flush all rate limits but keep session/queue
redis-cli -n 1 FLUSHDB  # Only REDIS_CACHE_DB
```

---

## Trusted Proxy Configuration

### Security-Critical Requirement

Rate limiting keys include client IP addresses. **If proxy configuration is wrong, all limits break**:

| Misconfiguration | Consequence |
|---|---|
| No trusted proxy behind LB | All requests appear from load balancer IP; limits apply to entire platform |
| Trust all proxies (`*`) | Attacker spoofs `X-Forwarded-For` to bypass IP-based limits |
| Wrong proxy CIDR | Legitimate traffic bypasses limits or gets blocked incorrectly |

### Laravel TrustedProxies Setup

**Install** (if not present):
```bash
composer require fruitcake/laravel-trusted-proxies
php artisan vendor:publish --provider="Fruitcake\TrustedProxies\TrustedProxiesServiceProvider"
```

**Configure** (`app/Http/Middleware/TrustProxies.php`):

```php
namespace App\Http\Middleware;

use Illuminate\Http\Middleware\TrustProxies as Middleware;
use Illuminate\Http\Request;

class TrustProxies extends Middleware
{
    /**
     * SECURITY: Only trust known infrastructure IPs.
     * DO NOT use '*' (trusts any proxy).
     */
    protected $proxies = [
        '10.0.1.0/24',      // Internal load balancer subnet
        '172.31.0.0/16',    // AWS VPC private range
        '103.21.244.0/22',  // Cloudflare IP range (example)
    ];

    /**
     * Trust these headers from proxies.
     */
    protected $headers =
        Request::HEADER_X_FORWARDED_FOR |
        Request::HEADER_X_FORWARDED_HOST |
        Request::HEADER_X_FORWARDED_PORT |
        Request::HEADER_X_FORWARDED_PROTO;
}
```

**AWS ELB/ALB**:
```php
protected $proxies = '*';  // AWS ALB IP is dynamic
// Safe because: VPC security group restricts access to ALB only
```

**Cloudflare**:
```php
protected $proxies = [
    // Get current ranges: https://www.cloudflare.com/ips/
    '173.245.48.0/20',
    '103.21.244.0/22',
    // ... (add all CF ranges)
];
```

**Verification Test**:

```php
// Test route (remove after verification)
Route::get('/test-ip', function (Request $request) {
    return [
        'request_ip'       => $request->ip(),           // Should be real client IP
        'remote_addr'      => $_SERVER['REMOTE_ADDR'],  // Proxy IP
        'x_forwarded_for'  => $request->header('X-Forwarded-For'),
        'trusted_proxies'  => config('trustedproxy.proxies'),
    ];
});
```

Expected output behind Cloudflare:
```json
{
  "request_ip": "203.0.113.45",         // Real client
  "remote_addr": "104.21.50.10",        // Cloudflare edge
  "x_forwarded_for": "203.0.113.45",
  "trusted_proxies": ["173.245.48.0/20", ...]
}
```

### IPv6 Considerations

**Challenge**: Privacy extensions (RFC 4941) rotate IPv6 addresses frequently.

**Current Behavior**: Package uses full IP address for keys. Legitimate IPv6 user rotating addresses gets fresh rate limit budgets but is still limited per account (composite strategy).

**Future Enhancement**: Normalize to `/64` prefix for IPv6:
```php
// Example (not yet implemented)
$normalizedIp = $this->normalizeIpv6($ipAddress);  // 2001:db8::/64
```

**Recommendation**: Use `composite` or `identifier` strategy for IPv6-heavy traffic. Pure `ip` strategy may be ineffective.

---

## Fail Mode Policy

### Philosophy: Security vs Availability Trade-off

When cache/Redis is **unavailable**, the system must choose:

1. **Fail Closed** (deny): Reject requests to preserve security
2. **Fail Safe** (allow): Continue without rate limiting to preserve availability

**No universal answer**: Choice depends on feature criticality and business requirements.

### Feature-Specific Fail Modes

| Feature | Default Behavior | Fail Mode | Rationale |
|---|---|---|---|
| **Login** | Check rate limit | **Fail Safe** (allow) | Availability: Users locked out causes support surge |
| **OTP Request** | Check rate limit | **Fail Closed** (deny) | Security: OTP flooding enables account takeover |
| **OTP Verify** | Check rate limit | **Fail Closed** (deny) | Security: Brute force OTP codes |
| **Password Reset** | Check rate limit | **Fail Closed** (deny) | Security: Token enumeration/flooding |
| **2FA Setup** | Check rate limit | **Fail Closed** (deny) | Security: TOTP secret flooding |
| **Registration** | Check rate limit | **Fail Safe** (allow) | Availability: Signups are revenue-critical |
| **API Token Creation** | Check rate limit | **Fail Closed** (deny) | Security: Token flooding |
| **Passkey Challenge** | Check rate limit | **Fail Safe** (allow) | Availability: Phishing-resistant anyway |

### Implementation Status

**Current (v1.10.0)**: All features fail safe (cache unavailable = no rate limiting).

**Recommendation**: Implement per-feature fail mode configuration:

```php
// Future config structure (not yet implemented)
'rate_limits' => [
    'login' => [
        'enabled'       => true,
        'max_attempts'  => 5,
        'decay_minutes' => 1,
        'strategy'      => 'composite',
        'fail_mode'     => 'safe',  // allow when cache down
    ],
    'otp_request' => [
        'enabled'       => true,
        'max_attempts'  => 3,
        'decay_minutes' => 5,
        'strategy'      => 'composite',
        'fail_mode'     => 'closed',  // deny when cache down
    ],
],
```

### Detecting Cache Failures

**Health Check**:
```php
// Add to monitoring
try {
    Cache::store('redis')->increment('health_check_counter');
    $healthy = true;
} catch (\Exception $e) {
    Log::critical('Cache unavailable for rate limiting', [
        'exception' => $e->getMessage(),
    ]);
    $healthy = false;
}
```

**Alert on**:
- Redis connection failures
- Redis memory exhaustion
- Slow cache operations (>100ms p95)

---

## Performance Impact

### Latency Overhead

**Per request overhead**:
- Cache check (Redis local): **1-2ms**
- Cache check (Redis cluster): **3-5ms**
- Cache check (Redis cross-region): **15-50ms**

**Login request breakdown**:
```
Total: 250ms
├─ Rate limit check: 2ms (0.8%)
├─ Database query: 15ms (6%)
├─ Password hash verify: 200ms (80%)  ← Dominant cost
└─ Session write: 33ms (13.2%)
```

**Conclusion**: Rate limiting overhead is negligible compared to password hashing.

### Throughput Impact

**Single Redis instance capacity**:
- Simple GET/SET: ~100,000 ops/sec
- INCR (atomic): ~80,000 ops/sec
- Authentication rate limiting: ~40,000 login attempts/sec (2 Redis ops per attempt)

**Bottleneck is NOT rate limiting** — it's password hashing (CPU-bound, ~5 logins/sec per core with bcrypt rounds=12).

### Memory Consumption

**Per counter**:
- Key: `auth_rl:login:comp:sha256hash` (~80 bytes)
- Value: Integer counter (~8 bytes)
- TTL metadata: ~16 bytes
- **Total: ~104 bytes per active rate limit key**

**Example load**:
- 10,000 unique identifier+IP combinations
- 5 features (login, otp_request, otp_verify, registration, 2fa)
- 50,000 active keys × 104 bytes = **5.2 MB**

**Redis memory for 1M daily users**: ~50 MB (negligible).

### Scaling Strategy

| Traffic | Architecture | Notes |
|---|---|---|
| <100 logins/sec | Single Redis | Sufficient for most deployments |
| 100-1000 logins/sec | Redis cluster (3 nodes) | HA + horizontal scaling |
| >1000 logins/sec | Redis cluster + read replicas | Also scale app servers and password hash workers |

**Horizontal scaling**: Add application servers first (password hashing is bottleneck), then Redis if cache becomes saturated.

---

## Rate Limit Strategies

### Strategy Comparison

| Strategy | Key | Use Case | Bypass Risk |
|---|---|---|---|
| **composite** | `sha256(identifier\|ip)` | Default; balances security and UX | Attacker can rotate IP or identifier |
| **identifier** | `sha256(identifier)` | Mobile users (NAT, dynamic IP) | Attacker can rotate identifiers |
| **ip** | `ip_address` | Anonymous endpoints (registration) | Attacker can rotate IPs (proxy, botnet) |

### Feature-Specific Recommendations

**Login**:
```php
'login' => [
    'strategy' => 'composite',  // Best default
    'max_attempts' => 5,
    'decay_minutes' => 1,
],
```

**OTP Request** (prevent identifier enumeration):
```php
'otp_request' => [
    'strategy' => 'ip',  // Limit per IP regardless of identifier
    'max_attempts' => 10,  // Higher threshold (multiple users may share IP)
    'decay_minutes' => 5,
],
```

**Registration** (prevent bot signups):
```php
'registration' => [
    'strategy' => 'ip',
    'max_attempts' => 5,
    'decay_minutes' => 60,  // Long cooldown
],
```

**2FA Verify** (prevent brute force of TOTP codes):
```php
'two_factor' => [
    'strategy' => 'composite',  // User-specific budget
    'max_attempts' => 5,
    'decay_minutes' => 5,
],
```

### Enterprise Multi-Dimensional Policy (v1.10.0+)

For advanced deployments requiring independent limits per dimension:

```php
'security' => [
    'abuse_policy' => [
        'enabled' => true,
        'dimensions' => [
            // Account protection (survives IP rotation)
            'account' => [
                'key'          => 'identifier',
                'max_attempts' => 10,
                'decay_minutes'=> 5,
            ],
            // Network protection (survives identifier rotation)
            'network' => [
                'key'          => 'ip',
                'max_attempts' => 50,
                'decay_minutes'=> 5,
            ],
            // Composite (existing behavior)
            'account_network' => [
                'key'          => 'composite',
                'max_attempts' => 5,
                'decay_minutes'=> 1,
            ],
        ],
    ],
],
```

**When to Enable**:
- Facing distributed credential stuffing (many IPs targeting specific accounts)
- Need independent budgets for account and network dimensions
- Require granular telemetry per dimension

**Trade-off**: More Redis operations (one per dimension) and higher memory usage.

---

## Emergency Controls

### 1. Global Rate Limit Kill Switch

**Use Case**: Emergency disable during false positive flood or Redis outage.

**Method 1**: Configuration Override (requires deploy)
```php
// config/authentication.php
'security' => [
    'rate_limits' => [
        'login' => [
            'enabled' => false,  // Disable login rate limiting
        ],
    ],
],
```

**Method 2**: Runtime Override (immediate, no deploy)
```bash
# Set emergency flag in Redis
redis-cli SET auth:emergency:disable_rate_limiting 1

# Application checks this flag before rate limiting
if (Cache::get('auth:emergency:disable_rate_limiting')) {
    return true;  // Allow all
}
```

**Implementation** (add to `FeatureRateLimiter::tooManyAttempts`):
```php
public function tooManyAttempts(string $feature, ?string $identifier, string $ipAddress): bool
{
    // Emergency kill switch
    if (Cache::get('auth:emergency:disable_rate_limiting')) {
        Log::warning("Rate limiting disabled via emergency flag", ['feature' => $feature]);
        return false;  // Never throttle
    }
    
    // Normal logic...
}
```

### 2. Clear Specific Rate Limits

**Clear all login limits**:
```bash
redis-cli --scan --pattern "auth_rl:login:*" | xargs redis-cli DEL
```

**Clear specific user**:
```php
// Artisan command: php artisan auth:clear-limits user@example.com
$identifier = 'user@example.com';
$ipAddress = '203.0.113.5';

// Clear all strategies for this user
Cache::forget("auth_rl:login:comp:" . hash('sha256', "{$identifier}|{$ipAddress}"));
Cache::forget("auth_rl:login:id:" . hash('sha256', $identifier));
Cache::forget("auth_rl:login:ip:{$ipAddress}");
```

### 3. Adjust Limits at Runtime

**Temporary threshold increase**:
```php
// Override config at runtime (survives until cache cleared)
Cache::put('auth:rate_limits:login:max_attempts', 20, now()->addHours(1));

// FeatureRateLimiter checks override first
$maxAttempts = Cache::get('auth:rate_limits:login:max_attempts') 
    ?? $this->config->getRateLimitConfig('login')['max_attempts'];
```

### 4. Block Specific IP Ranges

**For ongoing attack from known subnet**:
```php
// Middleware or early check
$blockedRanges = Cache::get('auth:blocked_ip_ranges', []);

foreach ($blockedRanges as $cidr) {
    if ($this->ipInRange($request->ip(), $cidr)) {
        abort(403, 'Access denied');
    }
}
```

**Set blocked ranges**:
```bash
redis-cli SET auth:blocked_ip_ranges '["203.0.113.0/24","198.51.100.0/24"]'
```

---

## Monitoring & Alerts

### Key Metrics

**Rate Limiting Metrics**:
```php
// Emit to monitoring (Prometheus, Datadog, CloudWatch)
Metrics::increment('auth.rate_limit.hit', [
    'feature' => 'login',
    'strategy' => 'composite',
]);

Metrics::increment('auth.rate_limit.blocked', [
    'feature' => 'login',
    'reason' => 'max_attempts_exceeded',
]);

Metrics::gauge('auth.rate_limit.active_keys', Redis::keys('auth_rl:*'));
```

**Queries to Monitor**:
1. **Throttle rate**: `sum(rate(auth.rate_limit.blocked[5m])) by (feature)`
   - Alert if login blocks >100/min (potential attack or false positive)

2. **Cache hit rate**: `cache.redis.hits / (cache.redis.hits + cache.redis.misses)`
   - Alert if <90% (cache warming issue or key churn)

3. **Redis latency**: `p95(redis.command.duration)`
   - Alert if >50ms (network or memory pressure)

4. **Rate limit key count**: `count(keys matching "auth_rl:*")`
   - Alert if sudden 10× spike (DDoS or bug)

### Grafana Dashboard

**Panels**:
- Rate limit blocks per minute (by feature)
- Active rate limit keys (gauge)
- Redis memory usage (GB)
- Cache operation latency (p50, p95, p99)
- Distinct IPs hitting limits (cardinality)

**Example PromQL**:
```promql
# Login blocks per minute
rate(auth_rate_limit_blocked_total{feature="login"}[5m]) * 60

# Top blocked IPs
topk(10, sum by (ip_hash) (auth_rate_limit_blocked_total))
```

### Logging

**Structured logs**:
```php
Log::info('Rate limit exceeded', [
    'feature'     => 'login',
    'strategy'    => 'composite',
    'key_hash'    => hash('sha256', $key),  // Never log raw identifier or IP
    'attempts'    => 6,
    'max_allowed' => 5,
    'retry_after' => 60,
    'user_agent'  => $request->userAgent(),
]);
```

**Do NOT log**:
- Raw email addresses or usernames
- Raw IP addresses (use hash or /24 prefix)
- Passwords or tokens (obviously)

### Alert Thresholds

| Alert | Threshold | Severity | Action |
|---|---|---|---|
| Login blocks >200/min | 5 min | Warning | Investigate for attack or false positive |
| Login blocks >1000/min | 1 min | Critical | Possible DDoS; engage WAF |
| Redis down | Immediate | Critical | Fail mode activates; restore cache |
| Redis memory >80% | Sustained 5 min | Warning | Increase memory or reduce TTLs |
| Rate limit keys >1M | Any | Warning | Investigate key leak or attack |

---

## Production Checklist

| # | Item | Verification | Status |
|---|---|---|---|
| **1** | Redis installed and running | `redis-cli PING` returns `PONG` | ☐ |
| **2** | Laravel cache driver set to `redis` | `config('cache.default')` = `redis` | ☐ |
| **3** | Redis dedicated database for rate limits | `REDIS_CACHE_DB` configured | ☐ |
| **4** | Redis persistence enabled (RDB or AOF) | Check `redis.conf`: `save` and `appendonly` | ☐ |
| **5** | Redis password set | `REDIS_PASSWORD` configured; `requirepass` in redis.conf | ☐ |
| **6** | Trusted proxies configured | `config('trustedproxy.proxies')` = infrastructure IPs | ☐ |
| **7** | Proxy configuration tested | `Request::ip()` returns real client IP, not proxy IP | ☐ |
| **8** | Rate limits enabled per feature | `config('authentication.security.rate_limits.*.enabled')` | ☐ |
| **9** | Rate limit strategies chosen | Composite for login, IP for registration, etc. | ☐ |
| **10** | Fail mode policy documented | Decision table filled out for each feature | ☐ |
| **11** | Emergency kill switch implemented | Runtime override mechanism tested | ☐ |
| **12** | Monitoring dashboards created | Grafana/Datadog showing rate limit metrics | ☐ |
| **13** | Alerts configured | Slack/PagerDuty for critical thresholds | ☐ |
| **14** | Load testing completed | Simulated attack blocked; legitimate traffic passes | ☐ |
| **15** | Redis failover tested | Simulated Redis outage; fail mode behaves as expected | ☐ |
| **16** | Runbook documented | Team knows how to clear limits, adjust thresholds | ☐ |
| **17** | Key prefix isolation verified | `auth_rl:*` keys separate from app cache | ☐ |
| **18** | IPv6 handling tested | IPv6 clients rate limited correctly | ☐ |
| **19** | Shared NAT scenario tested | Office network with 100+ users doesn't lock out | ☐ |
| **20** | Rollback procedure tested | Can disable rate limiting without deploy | ☐ |

---

## Rollback Procedures

### Scenario 1: False Positives Blocking Legitimate Users

**Symptoms**:
- Support tickets: "I can't log in"
- Logs show legitimate users hitting rate limits
- Metrics: Abnormally high block rate

**Immediate Mitigation** (in order):

**Step 1**: Clear all rate limit counters (30 seconds)
```bash
redis-cli --scan --pattern "auth_rl:*" | xargs redis-cli DEL
# Or: redis-cli -n 1 FLUSHDB  # If using dedicated DB
```

**Step 2**: Increase thresholds temporarily (1 minute)
```php
// Add to AppServiceProvider::boot() or hotpatch
Cache::put('auth:rate_limits:login:max_attempts', 50, now()->addHours(4));
```

**Step 3**: Disable problematic strategy (5 minutes, requires deploy)
```php
// config/authentication.php
'rate_limits' => [
    'login' => [
        'enabled' => false,  // Disable entirely
        // OR change strategy
        'strategy' => 'identifier',  // Less strict than composite
    ],
],
```
```bash
php artisan config:cache
php artisan optimize
# Deploy to all servers
```

**Step 4**: Root cause analysis (after mitigation)
- Check trusted proxy configuration (most common cause)
- Verify Redis is atomic (not file/array cache)
- Review recent config changes
- Analyze logs for common patterns in blocked requests

### Scenario 2: Redis Cache Outage

**Symptoms**:
- Redis unreachable: `Connection refused` or `Connection timeout`
- All rate limiting bypassed (fail-safe mode)
- Metrics: Rate limit checks return immediate `allow`

**Immediate Actions**:

**Step 1**: Restore Redis (priority 1)
```bash
# Check Redis status
sudo systemctl status redis
sudo systemctl start redis

# Or restart
sudo systemctl restart redis

# Verify connectivity
redis-cli PING
```

**Step 2**: If Redis cannot be restored immediately, choose:

**Option A**: Accept fail-safe mode (no rate limiting until Redis restored)
- Document in incident log
- Monitor for abuse (increased failed login rate)
- Engage WAF/CDN-level rate limiting as backup

**Option B**: Enable fail-closed mode for security-critical features (requires code change)
```php
// Emergency patch: Deny access when cache unavailable
public function tooManyAttempts(string $feature, ?string $identifier, string $ipAddress): bool
{
    try {
        $rateConfig = $this->config->getRateLimitConfig($feature);
        // ... normal logic
    } catch (\RedisException $e) {
        // Fail closed for OTP, fail safe for login
        if (in_array($feature, ['otp_request', 'otp_verify', 'two_factor'])) {
            Log::critical("Rate limiting fail-closed due to cache unavailable", [
                'feature' => $feature,
            ]);
            return true;  // Throttle (deny)
        }
        return false;  // Allow
    }
}
```

**Step 3**: Restore Redis from backup (if data lost)
```bash
# Redis RDB backup
cp /var/lib/redis/dump.rdb.backup /var/lib/redis/dump.rdb
sudo systemctl start redis

# Or AOF
redis-cli BGREWRITEAOF
```

**Step 4**: Validate counters after restore
```bash
# Check key count
redis-cli DBSIZE

# Sample keys
redis-cli --scan --pattern "auth_rl:*" --count 10
```

### Scenario 3: Distributed Attack Bypassing Rate Limits

**Symptoms**:
- High login failure rate despite rate limiting enabled
- Attacker rotating IPs or identifiers
- Metrics: Distributed pattern (many IPs, low per-IP rate)

**Immediate Mitigation**:

**Step 1**: Enable upstream WAF rate limiting (fastest, no deploy)
- Cloudflare: Dashboard → Security → WAF → Rate Limiting Rules
- AWS WAF: Create rate-based rule (IP, 100 req/5 min)
- Nginx: `limit_req_zone` + `limit_req` directive

**Step 2**: Enable enterprise multi-dimensional policy (requires config change)
```php
'security' => [
    'abuse_policy' => [
        'enabled' => true,
        'dimensions' => [
            'account' => [
                'key'           => 'identifier',
                'max_attempts'  => 10,  // Per account across all IPs
                'decay_minutes' => 5,
            ],
            'network' => [
                'key'           => 'ip',
                'max_attempts'  => 50,  // Per IP across all identifiers
                'decay_minutes' => 5,
            ],
        ],
    ],
],
```

**Step 3**: Enable account lockout (persistent, survives rate limit reset)
```php
'security' => [
    'account_lockout' => [
        'enabled'               => true,
        'max_failed_attempts'   => 5,
        'lockout_duration_mins' => 15,
    ],
],
```

**Step 4**: Block attacker ASN/country (if pattern clear)
```php
// Middleware or TrustProxies
$blockedAsns = [64496, 13335];  // Example ASNs
if (in_array($this->getAsn($request->ip()), $blockedAsns)) {
    abort(403);
}
```

### Scenario 4: Rate Limit Configuration Error After Deploy

**Symptoms**:
- Config syntax error or invalid strategy name
- Application errors: `InvalidStrategyException`
- All authentication requests failing

**Immediate Rollback**:

**Step 1**: Revert application to previous version (fastest)
```bash
# Docker/Kubernetes
kubectl rollout undo deployment/laravel-app

# Traditional deploy
cd /var/www/html
git reset --hard previous_commit_hash
php artisan config:cache
sudo systemctl reload php-fpm
```

**Step 2**: Or hotfix config (if rollback not possible)
```bash
# Edit config directly on servers
vim config/authentication.php

# Revert problematic change
# Then recache
php artisan config:cache
```

**Step 3**: Verify fix
```bash
php artisan config:show authentication.security.rate_limits
# Should show valid configuration

# Test login
curl -X POST https://your-app.com/login \
  -d "identifier=test@example.com&password=test"
# Should succeed or fail with proper error (not 500)
```

---

## Troubleshooting

### Problem: All Users Appear as Same IP

**Symptom**: Rate limit hit after 5 total requests across all users.

**Cause**: Trusted proxy not configured; Laravel sees load balancer IP.

**Diagnosis**:
```php
Route::get('/debug-ip', function (Request $request) {
    return [
        'request_ip' => $request->ip(),  // Should be unique per user
        'remote_addr' => $_SERVER['REMOTE_ADDR'],  // May be load balancer
    ];
});
```

**Fix**:
```php
// app/Http/Middleware/TrustProxies.php
protected $proxies = ['10.0.1.0/24'];  // Add load balancer subnet
```

---

### Problem: Rate Limits Not Working (Unlimited Attempts)

**Symptom**: Attacker makes 100+ attempts without being blocked.

**Diagnosis**:
```bash
# Check cache driver
php artisan tinker
>>> config('cache.default')
# Must be 'redis', not 'file' or 'array'

# Check Redis connectivity
>>> Cache::increment('test_counter')
# Should return 1, 2, 3... on repeated calls

# Check rate limit config
>>> config('authentication.security.rate_limits.login.enabled')
# Must be true
```

**Common Causes**:
1. **Cache driver is `file` or `array`**: Non-atomic, allows races
   - Fix: `CACHE_DRIVER=redis` in `.env`, restart app

2. **Rate limiting disabled in config**:
   - Fix: Set `enabled => true`

3. **Strategy mismatch**: Config says `composite`, but test uses different IP each time
   - Fix: Test with consistent IP+identifier pair

4. **Cache keys being cleared**: External process or cronjob flushing cache
   - Fix: Use dedicated Redis database for rate limits

---

### Problem: Legitimate User Locked Out

**Symptom**: User cannot log in after mistyping password 5 times.

**Immediate Fix**:
```php
// Artisan command or admin panel
use Vendor\LaravelAuthentication\Services\Security\FeatureRateLimiter;

$limiter = app(FeatureRateLimiter::class);
$limiter->clear('login', 'user@example.com', '203.0.113.5');

// Or clear all for this user (all IPs)
redis-cli --scan --pattern "auth_rl:login:*:$(echo -n 'user@example.com' | sha256sum | cut -d' ' -f1)" | xargs redis-cli DEL
```

**Prevention**:
- Increase threshold: `max_attempts => 10`
- Reduce cooldown: `decay_minutes => 1` (1 minute vs 5 minutes)
- Use `identifier` strategy (IP changes don't reset counter, but also doesn't lock out entire shared IP)

---

### Problem: Redis Memory Exhausted

**Symptom**: `OOM command not allowed when used memory > 'maxmemory'`

**Diagnosis**:
```bash
redis-cli INFO memory
# used_memory_human: 2.00G
# maxmemory_human: 1.00G  ← Problem
```

**Immediate Fix**:
```bash
# Increase memory limit
redis-cli CONFIG SET maxmemory 4gb
redis-cli CONFIG SET maxmemory-policy allkeys-lru

# Make permanent
echo "maxmemory 4gb" >> /etc/redis/redis.conf
echo "maxmemory-policy allkeys-lru" >> /etc/redis/redis.conf
```

**OR Clear old rate limit keys**:
```bash
# Delete expired keys (if TTL not being respected)
redis-cli --scan --pattern "auth_rl:*" | \
  while read key; do
    ttl=$(redis-cli TTL "$key")
    if [ "$ttl" -eq -1 ]; then
      redis-cli DEL "$key"  # No TTL set (bug)
    fi
  done
```

---

### Problem: IPv6 Users Bypassing Rate Limits

**Symptom**: Attacker makes unlimited attempts from IPv6 address that rotates every request.

**Cause**: IPv6 privacy extensions (RFC 4941) rotate addresses.

**Current Limitation**: Package uses full IP address; no prefix normalization yet.

**Workaround**:
```php
// Option 1: Use 'identifier' strategy (ignore IP)
'login' => [
    'strategy' => 'identifier',  // Limit per email/username
],

// Option 2: Enable account lockout (persistent)
'account_lockout' => [
    'enabled' => true,
    'max_failed_attempts' => 5,
],
```

**Future Enhancement**: Normalize IPv6 to `/64` prefix before hashing.

---

### Problem: Rate Limit Keys Never Expire

**Symptom**: Millions of rate limit keys accumulating in Redis.

**Cause**: TTL not set on keys (bug) or Redis eviction policy wrong.

**Diagnosis**:
```bash
# Check TTL on sample key
redis-cli TTL "auth_rl:login:comp:abc123"
# Should return seconds remaining, not -1 (no expiry)

# Count keys with no TTL
redis-cli --scan --pattern "auth_rl:*" | \
  while read key; do redis-cli TTL "$key"; done | \
  grep -c "^-1$"
```

**Fix**:
```bash
# Set TTL on orphaned keys (5 minutes = 300 seconds)
redis-cli --scan --pattern "auth_rl:*" | \
  while read key; do
    redis-cli EXPIRE "$key" 300
  done
```

**Prevention**: Ensure `RateLimiter::hit($key, $decaySeconds)` is called (not just `increment`).

---

## Summary

**Must-Have for Production**:
1. ✅ Redis with atomic operations (not file/array cache)
2. ✅ Trusted proxy configuration matching infrastructure
3. ✅ Monitoring and alerts for throttle rate and cache health
4. ✅ Documented fail mode policy per feature
5. ✅ Emergency procedures (kill switch, clear limits, rollback)

**Performance**:
- Rate limiting adds <2ms latency (negligible)
- Memory usage: ~100 bytes per active key (~5 MB for 50k keys)
- Redis is NOT the bottleneck (password hashing is)

**Fail Modes**:
- Default: Fail safe (allow) when cache unavailable
- Recommendation: Fail closed for security-critical features (OTP, password reset, token issuance)

**Rollback**:
- Fast: Clear Redis keys (`FLUSHDB` or pattern delete)
- Medium: Disable rate limiting via config
- Slow: Application rollback to previous version

---

## References

- [Rate Limiting Threat Model](./rate-limiting-threat-model.md)
- [Production Deployment Guide](../PRODUCTION-DEPLOYMENT.md)
- [Laravel Rate Limiting Docs](https://laravel.com/docs/rate-limiting)
- [Redis Atomic Operations](https://redis.io/commands/incr)
- [Trusted Proxies Package](https://github.com/fruitcake/laravel-trusted-proxies)
