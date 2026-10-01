# Security Audit Report: Enterprise Features
**Date**: 2026-10-01  
**Scope**: Circuit Breaker, Async Audit Logging, Health Checks, Session Pruning  
**Risk Level**: HIGH (2 critical, 2 medium)

---

## CRITICAL-01: Circuit Breaker Timing Side-Channel
**Component**: `src/Support/CircuitBreaker.php`  
**Risk**: Information Disclosure (OAuth provider availability)  
**CVSS**: 5.3 (Medium-High)

### Issue
Lines 61-71 create measurable timing difference:
- Circuit OPEN + timeout expired: `shouldAttemptReset()` + transition + attempt (~50-200ms)
- Circuit OPEN + timeout NOT expired: immediate exception (~1-5ms)

Attacker probes `oauth.google` circuit state by measuring response time:
```php
// Fast response = circuit open, timeout not expired (Google is down)
// Slow response = circuit attempting reset (Google may be recovering)
```

`getMetrics()` (line 124-131) exposes `opened_at` timestamp publicly - direct information disclosure.

### Attack Vector
```php
for ($i = 0; $i < 100; $i++) {
    $start = microtime(true);
    try {
        $service->authenticateWithGoogle($user);
    } catch (CircuitBreakerOpenException $e) {
        $timing = microtime(true) - $start;
        if ($timing < 0.01) {
            echo "Google OAuth definitely down\n";
        } else {
            echo "Google OAuth recovering - circuit testing\n";
        }
    }
}
```

### Fix Required
1. Normalize timing: add `usleep()` to fast path
2. Remove `opened_at` from public `getMetrics()` or require auth
3. Add jitter to timeout checks

---

## CRITICAL-02: Audit Job Data Loss on Queue Failure
**Component**: `src/Jobs/RecordAuthenticationAuditJob.php`  
**Risk**: Permanent audit trail loss (compliance violation)  
**CVSS**: 7.1 (High)

### Issue
Job has ZERO resilience configuration:
- No `$tries` property (defaults to 1 attempt)
- No `$maxExceptions` limit
- No `$backoff` exponential retry
- No `failed()` handler
- No idempotency checks

### Data Loss Scenarios

#### Scenario 1: Queue Worker Crash
```
1. Job dispatched successfully
2. Worker pulls job from queue
3. Worker crashes mid-execution (OOM, SIGKILL)
4. Job marked failed, audit data LOST forever
```

#### Scenario 2: Database Deadlock
```php
// handle() line 48
$attemptRepo->record($attemptData); // Deadlock exception
// Job fails, retry exhausted (1 attempt), data LOST
```

#### Scenario 3: Redis Connection Failure
```php
// AuthenticationAuditService.php line 79
RecordAuthenticationAuditJob::dispatch(...); // Redis down
// Dispatch fails, fallback='ignore' in config
// Audit data LOST silently
```

#### Scenario 4: Serialization Failure
```
Job payload contains non-serializable object in metadata
SerializationException → job never reaches queue
Data LOST with no fallback execution
```

### Impact
- SOC 2 / HIPAA / PCI-DSS compliance failure
- Forensic investigation impossible after breach
- No record of unauthorized access attempts

### Fix Required
1. Add retry logic: `public $tries = 3; public $backoff = [5, 15, 30];`
2. Implement `failed()` method → write to emergency log file
3. Add idempotency key to prevent duplicate writes on retry
4. Add job-level try-catch with local file fallback

---

## MEDIUM-01: Health Check Information Disclosure
**Component**: `src/Console/Commands/HealthCheckCommand.php`  
**Risk**: Architecture reconnaissance (aid to targeted attacks)  
**CVSS**: 4.2 (Medium)

### Issue
Error messages expose internal implementation:
```
Line 130: "Required table 'authentication_attempts' not found. Run migrations."
Line 143: "User model 'App\Models\User' not found"
Line 171: "Default strategy 'composite' not registered"
Line 177: "Strategy class 'App\Auth\CustomStrategy' not found"
```

### Attack Vector
```bash
# Attacker gains read access to Kubernetes pod logs
kubectl logs auth-pod-xxx | grep "Health check"
# Learns: table names, model paths, strategy names, database schema
# Uses this to craft SQL injection or privilege escalation
```

### Kubernetes Health Probe Context
```yaml
livenessProbe:
  exec:
    command: ["php", "artisan", "authentication:health"]
# Output logged to cluster → accessible to devs/operators
# In compromised cluster, attacker reads logs
```

### Fix Required
1. Add `--silent` flag for production probes (exit code only)
2. Sanitize error messages: "Database check failed" (no table names)
3. Move detailed errors to separate audit log (not stdout)

---

## MEDIUM-02: Session Prune Race Condition
**Component**: `src/Console/Commands/PruneSessionsCommand.php`  
**Risk**: Active user logout (availability impact)  
**CVSS**: 4.8 (Medium)

### Issue
Time-of-check-time-of-use (TOCTOU) race in `pruneDatabaseSessions()`:

```php
// Line 85-89
$query = DB::table($tableName)->where('last_activity', '<', $cutoff->timestamp);
$count = $query->count();  // ← Session X qualifies for deletion (inactive)

// ← USER MAKES REQUEST HERE, middleware updates last_activity for Session X

if ($count > 0 && !$dryRun) {
    $query->delete();      // ← Session X deleted despite now being active
}
// User sees "419 Session Expired" on next request
```

### Timeline
```
T+0ms:    Prune command reads sessions WHERE last_activity < cutoff
          Session ABC123 qualifies (last_activity = 25 days ago)
T+50ms:   User makes HTTP request with Session ABC123
          Middleware updates last_activity to NOW
T+100ms:  Prune command executes DELETE
          Session ABC123 deleted (stale query)
T+200ms:  User's next request → 419 Session Expired
```

### Reproduction
```bash
# Terminal 1: Start prune
php artisan authentication:prune-sessions --session-days=30

# Terminal 2: During count() phase, simulate user activity
php artisan tinker
> DB::table('sessions')->where('id', 'abc123')->update(['last_activity' => time()]);

# Session abc123 gets deleted despite being freshly active
```

### Fix Required
1. Use single-query `DELETE ... RETURNING` (PostgreSQL) or check `affected_rows`
2. Add `FOR UPDATE SKIP LOCKED` to prevent concurrent updates
3. Re-check `last_activity` in WHERE clause of DELETE

---

## Summary

| ID | Component | Issue | Data Loss | Timing Leak | Info Disclosure | Race | Fix Priority |
|----|-----------|-------|-----------|-------------|-----------------|------|--------------|
| CRITICAL-01 | CircuitBreaker | Timing side-channel | - | ✓ | ✓ | - | P0 |
| CRITICAL-02 | RecordAuditJob | No retry/fallback | ✓ | - | - | - | P0 |
| MEDIUM-01 | HealthCheck | Error messages leak internals | - | - | ✓ | - | P1 |
| MEDIUM-02 | PruneSessions | TOCTOU race | - | - | - | ✓ | P1 |

**Recommended Action**: Apply all fixes before production deployment.
