<?php

namespace App\Support\Health;

use App\Models\Branch;
use App\Models\Buyer;
use App\Models\JournalEntry;
use App\Models\Payment;
use App\Models\Sale;
use App\Models\Stand;
use App\Models\User;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Facades\App;
use Throwable;

/**
 * Runs the configured checks and describes the instance they ran on.
 *
 * There are two depths on purpose. Readiness runs the critical checks only and
 * is what a load balancer polls every few seconds, so it stays to a handful of
 * cheap round trips. Diagnostics runs everything, including the ledger scan,
 * and is for a person or an alerting monitor.
 */
class SystemHealth
{
    public function __construct(private Container $container) {}

    /** The critical checks: the ones that decide whether this instance should be sent traffic. */
    public function readiness(): HealthReport
    {
        return $this->run(criticalOnly: true);
    }

    /** Every check, including the ones too expensive to poll. */
    public function diagnostics(): HealthReport
    {
        return $this->run(criticalOnly: false);
    }

    /**
     * Who is answering. Deliberately reads nothing but configuration, so the
     * liveness endpoint can answer while every dependency is down.
     *
     * @return array{app: string, environment: string, version: string, time: string}
     */
    public function identity(): array
    {
        return [
            'app' => (string) config('app.name'),
            'environment' => (string) config('app.env'),
            'version' => (string) config('health.version'),
            'time' => now()->toIso8601String(),
        ];
    }

    /**
     * What this instance is running. Configuration and process facts only -
     * never credentials, hosts or connection strings.
     *
     * @return array<string, mixed>
     */
    public function runtime(): array
    {
        return [
            'php' => PHP_VERSION,
            'laravel' => App::version(),
            'debug' => (bool) config('app.debug'),
            'timezone' => (string) config('app.timezone'),
            'maintenanceMode' => App::isDownForMaintenance(),
            'configCached' => App::configurationIsCached(),
            'routesCached' => App::routesAreCached(),
            'eventsCached' => App::eventsAreCached(),
            'database' => (string) config('database.default'),
            'cacheStore' => (string) config('cache.default'),
            'queueConnection' => (string) config('queue.default'),
            'filesystemDisk' => (string) config('filesystems.default'),
            'memoryUsageMb' => round(memory_get_usage(true) / 1024 / 1024, 2),
            'memoryPeakMb' => round(memory_get_peak_usage(true) / 1024 / 1024, 2),
        ];
    }

    /**
     * How much is in the system. Sizes only - useful for spotting a database
     * that is empty when it should not be, or one that has been pointed at the
     * wrong environment.
     *
     * @return array<string, int|null>
     */
    public function footprint(): array
    {
        try {
            return [
                'branches' => Branch::query()->count(),
                'users' => User::query()->count(),
                'stands' => Stand::query()->count(),
                'buyers' => Buyer::query()->count(),
                'sales' => Sale::query()->count(),
                'payments' => Payment::query()->count(),
                'journalEntries' => JournalEntry::query()->count(),
            ];
        } catch (Throwable) {
            /** The database check has already reported why; a count is not worth a second failure. */
            return [];
        }
    }

    private function run(bool $criticalOnly): HealthReport
    {
        $results = [];

        foreach ((array) config('health.checks', []) as $class) {
            $check = $this->container->make($class);

            if ($criticalOnly && ! $check->isCritical()) {
                continue;
            }

            $results[] = $this->timed($check);
        }

        return new HealthReport($results);
    }

    /**
     * A check that throws is a failed check, not a failed request: one broken
     * dependency must not cost the operator the report on all the others.
     */
    private function timed(HealthCheck $check): CheckResult
    {
        $startedAt = hrtime(true);

        try {
            $result = $check->run();
        } catch (Throwable $exception) {
            $result = CheckResult::failed($check->name(), 'The check could not be completed.', [
                'error' => $exception->getMessage(),
            ]);
        }

        return $result->withTiming(
            (hrtime(true) - $startedAt) / 1_000_000,
            (float) config('health.thresholds.slow_ms'),
        );
    }
}
