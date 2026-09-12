<?php

namespace App\Support\Health;

/**
 * One thing worth knowing about a running instance.
 *
 * Checks are listed in `config/health.php` and resolved from the container, so
 * adding one is a class and a line of configuration rather than an edit to the
 * runner.
 */
interface HealthCheck
{
    /** The key this check is reported under. */
    public function name(): string;

    /**
     * Whether a failure here means the instance cannot serve requests.
     *
     * Critical checks are the ones the readiness endpoint runs, so keep them
     * cheap: a load balancer polls it every few seconds. Everything else is
     * reported by the diagnostics endpoint only.
     */
    public function isCritical(): bool;

    public function run(): CheckResult;
}
