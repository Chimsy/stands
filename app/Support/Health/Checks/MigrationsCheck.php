<?php

namespace App\Support\Health\Checks;

use App\Support\Health\CheckResult;
use App\Support\Health\HealthCheck;
use Throwable;

/**
 * Whether the schema in front of this instance is the one the code expects.
 *
 * A pending migration in production almost always means a deploy stopped half
 * way, and the symptom is a column-not-found exception on whichever endpoint
 * needs it first. Catching it here fails the instance out of rotation instead.
 */
final class MigrationsCheck implements HealthCheck
{
    public function name(): string
    {
        return 'migrations';
    }

    public function isCritical(): bool
    {
        return true;
    }

    public function run(): CheckResult
    {
        $migrator = app('migrator');

        try {
            if (! $migrator->getRepository()->repositoryExists()) {
                return CheckResult::failed(
                    $this->name(),
                    'The migration repository is missing: this database has never been migrated.',
                );
            }

            $ran = $migrator->getRepository()->getRan();
            $files = array_keys($migrator->getMigrationFiles(
                array_merge([database_path('migrations')], $migrator->paths()),
            ));
        } catch (Throwable $exception) {
            return CheckResult::failed($this->name(), 'The migration state could not be read.', [
                'error' => $exception->getMessage(),
            ]);
        }

        $pending = array_values(array_diff($files, $ran));

        if ($pending !== []) {
            return CheckResult::failed(
                $this->name(),
                sprintf('%d migration(s) have not been run.', count($pending)),
                ['ran' => count($ran), 'pending' => $pending],
            );
        }

        return CheckResult::ok($this->name(), 'The schema is up to date.', [
            'ran' => count($ran),
            'latest' => $ran === [] ? null : end($ran),
        ]);
    }
}
