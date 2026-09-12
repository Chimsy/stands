---
paths:
  - 'app/Support/Health/**'
---

# Health

## Health checks: three depths, and never leak detail to the open ones
Checks are registered in `config/health.php` and resolved from the container, so a new one is a class implementing `App\Support\Health\HealthCheck` plus a line of config - never an edit to `SystemHealth`.

`isCritical()` decides where it runs. A critical check runs on `/api/v1/health/ready`, which a load balancer polls every few seconds, so keep it to a round trip or two; anything that scans a table (the ledger check) must report itself non-critical and be left to `/api/v1/health/diagnostics`.

`CheckResult::$message` is read by unauthenticated callers - no hosts, drivers, credentials or exception text. Those go in `$detail`, which only the diagnostics endpoint renders, behind an administrator's token or the `X-Health-Token` secret.

A check that throws is caught and reported as failed: one broken dependency must not cost the operator the report on all the others. `/api/v1/health` itself must stay dependency-free so a database outage cannot have an orchestrator restart a healthy container.
