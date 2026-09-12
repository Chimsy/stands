<?php

namespace App\Support\Health\Checks;

use App\Support\Health\CheckResult;
use App\Support\Health\HealthCheck;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * How far behind the workers are.
 *
 * A backlog is not a reason to take an instance out of rotation - the web tier
 * is answering perfectly well - so this reports degraded and never fails
 * readiness. Failed jobs are counted too: they are the ones nobody notices
 * until a buyer asks where their receipt went.
 */
final class QueueCheck implements HealthCheck
{
    public function name(): string
    {
        return 'queue';
    }

    /** A backlog does not stop this instance serving requests. */
    public function isCritical(): bool
    {
        return false;
    }

    public function run(): CheckResult
    {
        $connection = config('queue.default');
        $driver = config("queue.connections.{$connection}.driver");

        /** Only the database driver keeps its backlog somewhere this process can count cheaply. */
        if ($driver !== 'database') {
            return CheckResult::ok($this->name(), 'The queue backlog is only measured on the database driver.', [
                'connection' => $connection,
                'driver' => $driver,
            ]);
        }

        try {
            $pending = DB::table(config("queue.connections.{$connection}.table", 'jobs'))->count();
            $failed = DB::table(config('queue.failed.table', 'failed_jobs'))->count();
            $oldest = DB::table(config("queue.connections.{$connection}.table", 'jobs'))->min('available_at');
        } catch (Throwable $exception) {
            return CheckResult::failed($this->name(), 'The queue tables could not be read.', [
                'connection' => $connection,
                'error' => $exception->getMessage(),
            ]);
        }

        $detail = [
            'connection' => $connection,
            'driver' => $driver,
            'pending' => $pending,
            'failed' => $failed,
            'oldestPendingSeconds' => $oldest === null ? null : max(0, now()->getTimestamp() - (int) $oldest),
        ];

        $maxPending = (int) config('health.thresholds.queue_backlog');
        $maxFailed = (int) config('health.thresholds.failed_jobs');

        if ($pending > $maxPending || $failed > $maxFailed) {
            return CheckResult::degraded(
                $this->name(),
                sprintf('%d job(s) are waiting and %d have failed.', $pending, $failed),
                [...$detail, 'thresholds' => ['pending' => $maxPending, 'failed' => $maxFailed]],
            );
        }

        return CheckResult::ok($this->name(), sprintf('%d job(s) waiting, %d failed.', $pending, $failed), $detail);
    }
}
