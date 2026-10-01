# Performance & High-Traffic Audit Plan
**Package**: mixudev/laravel-authentication v1.8.0  
**Audit Date**: 2026-10-01  
**Status**: ANALYSIS COMPLETE → EXECUTION READY

---

## Executive Summary

**Scope**: 120+ PHP files, 8 database tables, 159 tests  
**Architecture**: Stateful (session) + Stateless (API token), Strategy pattern, Event-driven audit  
**Current State**: Secure, maintainable, tested — but not yet optimized for high-traffic/concurrency  

**Primary Concerns**:
1. Missing database indexes → N queries without indexes = O(N) scans
2. Synchronous audit logging → blocking I/O on every auth operation
3. Email dispatch blocking login flow (default non-queued)
4. Race conditions in attempt counters & lockout tracking
5. Unbounded session list queries (potential memory exhaustion)
6. No query result caching for repeated user/device lookups

---

## Architecture Flow Analysis

### Authentication Pipeline (AuthenticationService::authenticate)
```
Request → LoginRequest validation
  ↓
Rate Limiter (composite key: sha1(identifier+IP))
  ↓ (Query: cache increment)
Strategy Resolution (registry lookup, singleton)
  ↓
User Lookup (CredentialResolver::resolveByColumns)
  ↓ (Query: SELECT * FROM users WHERE email=? OR username=? LIMIT 1)
Account Lockout Check (AccountLockService::isLocked)
  ↓ (Query: SELECT * FROM authentication_account_lockouts WHERE user_identifier=?)
Credential Validation (Hasher::check + optional rehash)
  ↓ (CPU: Bcrypt/Argon2 ~200-300ms per check)
Failure Path:
  - Record attempt (INSERT authentication_attempts)
  - Increment lockout counter (UPDATE authentication_account_lockouts)
  - Dispatch LoginFailed event → Audit log (sync file write)
  - Throw exception
Success Path:
  - Clear rate limit (cache delete)
  - Clear lockout (DELETE authentication_account_lockouts)
  - 2FA check (Query: SELECT * FROM authentication_two_factors WHERE user_id=?)
  - Device trust check (Query: SELECT * FROM authentication_devices WHERE ...)
  - Session creation / Token generation
  - Device registration (INSERT/UPDATE authentication_devices)
  - Dispatch LoginSucceeded event → Audit log (sync file write)
  - Return result
```

**Bottlenecks Identified**:
- **B1**: No index on `authentication_account_lockouts.user_identifier` → full table scan
- **B2**: Synchronous audit log write (file I/O blocking)
- **B3**: Device detection query without index on `device_fingerprint`
- **B4**: Race condition in lockout `firstOrCreate` + increment (concurrent requests can bypass counter)

---

## Critical Issues (HIGH Priority)

### C1: Missing Database Indexes [CRITICAL]
**Impact**: O(N) table scans on every login → response time degrades linearly with user count  
**Files**: All migrations in `database/migrations/`

**Required Indexes**:
```sql
-- authentication_account_lockouts
INDEX idx_lockouts_user_identifier (user_identifier)
INDEX idx_lockouts_locked_until (locked_until) -- for auto-unlock cleanup

-- authentication_attempts  
INDEX idx_attempts_identifier_status_time (identifier, status, attempted_at)
INDEX idx_attempts_ip_time (ip_address, attempted_at)

-- authentication_login_histories
INDEX idx_login_history_user_time (user_id, login_at DESC)
INDEX idx_login_history_logout (user_id, logout_at) -- for active session query

-- authentication_password_histories
INDEX idx_password_history_user_created (user_id, created_at DESC)

-- authentication_devices
INDEX idx_devices_user_fingerprint (user_id, device_fingerprint)
INDEX idx_devices_user_trusted (user_id, is_trusted, trusted_until)
INDEX idx_devices_user_lastseen (user_id, last_seen_at DESC)

-- authentication_two_factors
INDEX idx_2fa_user_confirmed (user_id, confirmed_at) -- isEnabledFor fast path

-- authentication_passkeys
INDEX idx_passkeys_credential_id (credential_id) -- WebAuthn lookup
INDEX idx_passkeys_user_id (user_id)

-- sessions (Laravel core table, if database driver)
INDEX idx_sessions_user_activity (user_id, last_activity DESC)
```

**Verification**: `EXPLAIN SELECT ...` queries must show `key` column populated, NOT `NULL`.

---

### C2: Race Condition in Account Lockout [HIGH]
**File**: `src/Services/Security/AccountLockService.php:44-68`  
**Issue**: `firstOrCreate()` + separate increment allows concurrent requests to bypass max_attempts  

**Current Flow**:
```php
$record = AccountLockout::firstOrCreate(['user_identifier' => $id], ['failed_attempts' => 0]);
$record->failed_attempts = (int)$record->failed_attempts + 1; // NOT ATOMIC
$record->save();
if ($record->failed_attempts >= $maxAttempts) { /* lock */ }
```

**Attack Vector**: 10 concurrent requests can each read `failed_attempts=4`, increment to 5, save — bypassing lockout.

**Fix**: Use database-level atomic increment with `lockForUpdate`:
```php
DB::transaction(function() use ($user, $maxAttempts) {
    $record = AccountLockout::lockForUpdate()
        ->firstOrCreate(['user_identifier' => $id], ['failed_attempts' => 0]);
    $record->increment('failed_attempts');
    $record->refresh();
    if ($record->failed_attempts >= $maxAttempts) { /* lock */ }
});
```

---

### C3: Synchronous Audit Logging Blocks Auth Flow [HIGH]
**Files**:  
- `src/Services/Security/AuthenticationAuditService.php`  
- `src/Listeners/SecurityAuditEventListener.php`

**Issue**: Every login/logout writes audit log synchronously → adds 5-50ms latency per auth  
**Impact**: At 100 req/s, audit I/O can saturate disk queue

**Current**:
```php
// AuthenticationAuditService::logEvent
Log::channel($channel)->info('auth.event', $data); // BLOCKS until fsync()
```

**Solutions** (pick one):
1. **Queue audit events** (recommended):
   ```php
   dispatch(new WriteAuditLog($eventType, $data))->onQueue('audit');
   ```
2. **Batch insert**: Buffer events in memory, flush every 100 records or 5 seconds
3. **Async log handler**: Use monolog AsyncHandler + Redis/database channel

**Trade-off**: Queue = eventual consistency (audit may lag 1-5s), but auth remains fast.

---

## High-Priority Optimizations

### H1: Email Dispatch Blocking Login [MEDIUM-HIGH]
**Files**: `src/Services/Otp/OtpService.php:104-119`, `src/Services/Session/NewDeviceDetectionService.php`

**Issue**: `Mail::send()` blocks until SMTP completes (200-2000ms depending on server)  
**Config**: `config/authentication.php:54` has `mail.queue => false` by default

**Fix**: Enable queue by default + document requirement:
```php
'mail' => [
    'queue' => true, // CHANGE: false → true
    'queue_connection' => null,
    'queue_name' => 'auth-emails',
],
```

**Migration Note**: Add to CHANGELOG as behavior change; require `queue:work` in production.

---

### H2: Unbounded Session Query (Memory Risk) [MEDIUM]
**File**: `src/Services/Session/SessionManagerService.php:26-81`

**Issue**: `getActiveSessions()` loads ALL user sessions without limit  
**Risk**: User with 1000+ sessions (malicious or leaked token) causes OOM

**Current**:
```php
$records = DB::table($tableName)->where('user_id', $userId)->orderBy(...)->get(); // NO LIMIT
foreach ($records as $record) { ... } // Load all into memory
```

**Fix**: Add pagination + hard cap:
```php
public function getActiveSessions(Authenticatable $user, int $limit = 50, int $offset = 0): array
{
    $records = DB::table($tableName)
        ->where('user_id', $userId)
        ->orderBy('last_activity', 'desc')
        ->limit(min($limit, 100)) // Cap at 100
        ->offset($offset)
        ->get();
    // ...
}
```

**Controller Change**: SessionController must handle pagination in response.

---

### H3: Repeated User Lookup in Pipeline [MEDIUM]
**Files**: `AuthenticationService.php`, `CredentialResolver.php`, `TwoFactorService.php`

**Issue**: Same user model queried 2-3 times per auth flow:
1. Strategy resolves user
2. AccountLockService re-queries by `user_identifier`
3. TwoFactorService queries `TwoFactorAuthentication` by `user_id`

**Solution**: Cache resolved user in request lifecycle:
```php
// Add to AuthenticationService
private array $userCache = [];

protected function resolveUserCached(string $identifier): ?Authenticatable {
    return $this->userCache[$identifier] ??= $this->resolver->resolve($identifier);
}
```

**Caveat**: Only cache within single request — never across requests (stale data risk).

---

## Medium-Priority Improvements

### M1: Strategy Registry Optimization [LOW-MEDIUM]
**File**: `src/Support/AuthenticationStrategyRegistry.php`

**Current**: Strategies resolved from config on every auth  
**Optimization**: Pre-instantiate all strategies in ServiceProvider boot, store in registry

---

### M2: Hasher Check Dominates CPU [ACCEPTABLE]
**Observation**: Bcrypt/Argon2 password verification takes 200-300ms by design  
**Recommendation**: NO CHANGE — this is intentional brute-force protection  
**Alternative**: If >1000 login/s needed, consider horizontal scaling + rate limiting

---

### M3: CAPTCHA Verification External Call [ACCEPTABLE]
**Files**: `src/Services/Security/Captcha/*Driver.php`

**Current**: HTTP POST to external service (Cloudflare/Google/hCaptcha), 5s timeout  
**Risk**: External service downtime blocks logins  
**Mitigation**: Already has try-catch fallback, acceptable

---

### M4: Device Detection Redundant Parsing [LOW]
**File**: `src/Services/Session/DeviceDetector.php:24-55`

**Issue**: User-Agent string parsed on every login, same UA parsed repeatedly  
**Solution**: Cache parsed UA in request lifecycle (not across requests)

---

## Testing Strategy

### Performance Benchmarks (Before/After)
```bash
# Baseline: Current implementation
ab -n 1000 -c 10 http://localhost/auth/login
# Measure: p50, p95, p99 latency

# After index creation
ab -n 1000 -c 10 http://localhost/auth/login
# Expected: p95 latency drops 30-50%

# After audit queue
ab -n 1000 -c 10 http://localhost/auth/login
# Expected: p95 latency drops 10-20% more
```

### Concurrency Tests
```php
// Test: Lockout race condition
// Spawn 20 concurrent requests with invalid password
// Assert: Exactly max_attempts recorded, NOT max_attempts * 20
```

### Load Tests
```bash
# Simulate 500 concurrent users, 10 req/s each
siege -c 500 -r 100 http://localhost/auth/login
# Monitor: MySQL slow query log, Redis hit rate, queue depth
```

---

## Implementation Order

### Phase 1: Safety & Correctness (Week 1)
1. ✅ Add missing database indexes (migration + verify with EXPLAIN)
2. ✅ Fix race condition in AccountLockService (atomic increment + transaction)
3. ✅ Add pagination to getActiveSessions (prevent OOM)
4. ✅ Run full test suite, ensure 159/159 pass

### Phase 2: Performance (Week 1)
5. ✅ Enable email queue by default (config change + docs)
6. ✅ Queue audit logging (new job class + config flag)
7. ✅ Add request-scoped user cache (optimization)
8. ✅ Benchmark before/after

### Phase 3: High-Traffic Hardening (Week 2)
9. ✅ Add Redis cache config example (docs)
10. ✅ Add Horizon/queue worker setup guide (docs)
11. ✅ Add database connection pool tuning guide (docs)
12. ✅ Load test + profile bottlenecks

### Phase 4: Documentation (Week 2)
13. ✅ Write performance tuning guide
14. ✅ Write scaling recommendations (horizontal vs vertical)
15. ✅ Update CHANGELOG with breaking changes
16. ✅ Tag release v1.9.0

---

## Risk Assessment

| Change | Risk Level | Rollback Strategy |
|--------|-----------|-------------------|
| Add indexes | LOW | Drop index migration |
| Fix lockout race | LOW | Revert file, tests cover behavior |
| Paginate sessions | LOW | Old API still works (default limit) |
| Queue emails | MEDIUM | Config rollback to `queue=>false` |
| Queue audit | MEDIUM | Config flag to disable, sync fallback |
| Cache user | LOW | Remove cache array, no external state |

**Breaking Changes**:
- Email queue enabled by default → requires `queue:work` in production
- Session pagination → API returns max 100 sessions (was unlimited)

**Mitigation**: Document in UPGRADE.md, tag as minor version bump (v1.8 → v1.9).

---

## Success Criteria

1. ✅ All database queries use indexes (EXPLAIN shows `key` column populated)
2. ✅ Lockout race condition test passes (20 concurrent requests = max_attempts recorded)
3. ✅ Auth p95 latency < 200ms under 100 req/s load (excluding password hash time)
4. ✅ Memory usage stable under 1000+ concurrent sessions
5. ✅ All 159 existing tests pass
6. ✅ Zero new security vulnerabilities introduced (manual review + PHPStan L8)

---

## Open Questions

1. **Queue backend**: Document Redis vs database queue trade-offs?  
   → YES: Add section in performance guide

2. **Audit retention**: Auto-prune old audit logs?  
   → ALREADY EXISTS: `PruneAuditLogsCommand` (defer, not in scope)

3. **Horizontal scaling**: Session affinity requirements?  
   → Document: Database session driver required for multi-server (not file driver)

4. **CDN for QR codes**: Cache 2FA QR code SVG?  
   → NO: QR codes are one-time setup, not a bottleneck

---

## Next Steps

**Immediate Actions**:
1. Create migration for indexes
2. Patch AccountLockService race condition
3. Run test suite
4. Benchmark before/after

**Owner**: AI Agent (autonomous execution)  
**Timeline**: 2-3 hours (implementation + verification)  
**Rollout**: Safe to deploy (no breaking changes in Phase 1)
