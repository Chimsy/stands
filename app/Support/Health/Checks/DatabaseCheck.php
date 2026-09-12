<?php

namespace App\Support\Health\Checks;

use App\Support\Health\CheckResult;
use App\Support\Health\HealthCheck;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * The database is the application: without it nothing can be read or sold, so
 * this is the first check to run and the one that decides readiness.
 */
final class DatabaseCheck implements HealthCheck
{
    public function name(): string
    {
        return 'database';
    }

    public function isCritical(): bool
    {
        return true;
    }

    public function run(): CheckResult
    {
        $connection = DB::connection();

        try {
            $connection->select('select 1');
        } catch (Throwable $exception) {
            return CheckResult::failed($this->name(), 'The database is not reachable.', [
                'connection' => $connection->getName(),
                'driver' => $connection->getDriverName(),
                'error' => $exception->getMessage(),
            ]);
        }

        return CheckResult::ok($this->name(), 'The database answered.', [
            'connection' => $connection->getName(),
            'driver' => $connection->getDriverName(),
            'database' => $connection->getDatabaseName(),
        ]);
    }
}
