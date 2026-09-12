<?php

namespace App\Support\Health\Checks;

use App\Support\Health\CheckResult;
use App\Support\Health\HealthCheck;
use Throwable;

/**
 * The directories the framework writes to while serving a request.
 *
 * `bootstrap/cache` is reported but not judged: an immutable deploy mounts it
 * read-only on purpose once the config is cached, and failing an instance for
 * that would take a perfectly healthy release out of rotation.
 */
final class StorageCheck implements HealthCheck
{
    public function name(): string
    {
        return 'storage';
    }

    public function isCritical(): bool
    {
        return true;
    }

    public function run(): CheckResult
    {
        $required = [
            'storage/framework' => storage_path('framework'),
            'storage/logs' => storage_path('logs'),
        ];

        $unwritable = array_keys(array_filter(
            $required,
            fn (string $path): bool => ! is_dir($path) || ! is_writable($path),
        ));

        $detail = [
            'checked' => array_keys($required),
            'bootstrapCacheWritable' => is_writable(base_path('bootstrap/cache')),
        ];

        if ($unwritable !== []) {
            return CheckResult::failed(
                $this->name(),
                sprintf('%d storage path(s) cannot be written to.', count($unwritable)),
                [...$detail, 'unwritable' => $unwritable],
            );
        }

        /** Writability can be granted and still fail on a full or read-only volume, so actually write. */
        $probe = storage_path('framework/health-check.tmp');

        try {
            if (file_put_contents($probe, (string) now()->getTimestampMs()) === false) {
                throw new \RuntimeException('The probe file could not be written.');
            }
        } catch (Throwable $exception) {
            return CheckResult::failed($this->name(), 'A probe file could not be written to storage.', [
                ...$detail,
                'error' => $exception->getMessage(),
            ]);
        } finally {
            if (is_file($probe)) {
                @unlink($probe);
            }
        }

        return CheckResult::ok($this->name(), 'The storage paths are writable.', [
            ...$detail,
            'defaultDisk' => config('filesystems.default'),
        ]);
    }
}
