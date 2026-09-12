<?php

namespace App\Support\Health\Checks;

use App\Support\Health\CheckResult;
use App\Support\Health\HealthCheck;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Throwable;

/**
 * A full round trip through the cache store, because a store that accepts
 * writes and returns nothing back is worse than one that is plainly down: the
 * login throttle and every cached lookup silently stop working.
 */
final class CacheCheck implements HealthCheck
{
    private const KEY_PREFIX = 'health-check:';

    public function name(): string
    {
        return 'cache';
    }

    public function isCritical(): bool
    {
        return true;
    }

    public function run(): CheckResult
    {
        $key = self::KEY_PREFIX.Str::random(16);
        $value = (string) now()->getTimestampMs();
        $store = config('cache.default');

        try {
            Cache::put($key, $value, now()->addMinute());
            $read = Cache::get($key);
            Cache::forget($key);
        } catch (Throwable $exception) {
            return CheckResult::failed($this->name(), 'The cache store is not reachable.', [
                'store' => $store,
                'error' => $exception->getMessage(),
            ]);
        }

        if ($read !== $value) {
            return CheckResult::failed(
                $this->name(),
                'The cache store accepted a write but did not return it.',
                ['store' => $store],
            );
        }

        return CheckResult::ok($this->name(), 'The cache store read back what it was given.', [
            'store' => $store,
        ]);
    }
}
