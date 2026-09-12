<?php

namespace App\Support\Health;

use App\Enums\HealthStatus;

/**
 * The outcome of one run of the checks: the worst status any of them reported,
 * and the results keyed by check name so a monitor can address one of them
 * without knowing the order they ran in.
 */
final readonly class HealthReport
{
    /**
     * @param  list<CheckResult>  $checks
     */
    public function __construct(public array $checks) {}

    public function status(): HealthStatus
    {
        return array_reduce(
            $this->checks,
            fn (HealthStatus $worst, CheckResult $check): HealthStatus => $check->status->isWorseThan($worst)
                ? $check->status
                : $worst,
            HealthStatus::Ok,
        );
    }

    public function durationMs(): float
    {
        return round(array_sum(array_map(fn (CheckResult $check): float => $check->durationMs, $this->checks)), 2);
    }

    /**
     * Statuses and safe messages only, for callers that are not authorised to
     * read the deployment's internals.
     *
     * @return array<string, array<string, mixed>>
     */
    public function summary(): array
    {
        return $this->keyed(fn (CheckResult $check): array => $check->summary());
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function detailed(): array
    {
        return $this->keyed(fn (CheckResult $check): array => $check->toArray());
    }

    /**
     * @param  callable(CheckResult): array<string, mixed>  $render
     * @return array<string, array<string, mixed>>
     */
    private function keyed(callable $render): array
    {
        $rendered = [];

        foreach ($this->checks as $check) {
            $rendered[$check->name] = $render($check);
        }

        return $rendered;
    }
}
