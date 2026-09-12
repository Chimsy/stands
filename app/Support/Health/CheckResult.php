<?php

namespace App\Support\Health;

use App\Enums\HealthStatus;

/**
 * What one health check found.
 *
 * `message` is written for anybody, including an unauthenticated monitor, so it
 * says what is wrong without saying where: no host names, no credentials, no
 * exception text. Anything that would help an attacker map the deployment goes
 * in `detail`, which only the authorised diagnostics endpoint renders.
 */
final readonly class CheckResult
{
    /**
     * @param  array<string, mixed>  $detail
     */
    private function __construct(
        public string $name,
        public HealthStatus $status,
        public string $message,
        public array $detail = [],
        public float $durationMs = 0.0,
    ) {}

    /**
     * @param  array<string, mixed>  $detail
     */
    public static function ok(string $name, string $message, array $detail = []): self
    {
        return new self($name, HealthStatus::Ok, $message, $detail);
    }

    /**
     * Working, but not well enough to leave alone.
     *
     * @param  array<string, mixed>  $detail
     */
    public static function degraded(string $name, string $message, array $detail = []): self
    {
        return new self($name, HealthStatus::Degraded, $message, $detail);
    }

    /**
     * @param  array<string, mixed>  $detail
     */
    public static function failed(string $name, string $message, array $detail = []): self
    {
        return new self($name, HealthStatus::Failed, $message, $detail);
    }

    /**
     * Stamps the measured duration on the result, demoting a check that
     * answered correctly but took longer than its budget: a database that
     * replies in two seconds is not healthy, and noticing that before it stops
     * replying altogether is the point of measuring at all.
     */
    public function withTiming(float $durationMs, float $budgetMs): self
    {
        $overBudget = $this->status === HealthStatus::Ok && $budgetMs > 0 && $durationMs > $budgetMs;

        return new self(
            $this->name,
            $overBudget ? HealthStatus::Degraded : $this->status,
            $overBudget
                ? sprintf('%s It took %.0fms, over the %.0fms budget.', $this->message, $durationMs, $budgetMs)
                : $this->message,
            $this->detail,
            round($durationMs, 2),
        );
    }

    /**
     * What an anonymous caller sees.
     *
     * @return array{status: string, message: string, durationMs: float}
     */
    public function summary(): array
    {
        return [
            'status' => $this->status->value,
            'message' => $this->message,
            'durationMs' => $this->durationMs,
        ];
    }

    /**
     * @return array{status: string, message: string, durationMs: float, detail: array<string, mixed>}
     */
    public function toArray(): array
    {
        return [...$this->summary(), 'detail' => $this->detail];
    }
}
