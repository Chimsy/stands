<?php

namespace App\Enums;

use Illuminate\Http\Response;

/**
 * How a health check, or a whole run of them, came out.
 *
 * The three are ranked: a run reports the worst status any of its checks did,
 * and only a failure is severe enough to answer 503 and have a load balancer
 * take the instance out of rotation. `Degraded` means the application is still
 * serving but something needs looking at - a slow database, a growing queue.
 */
enum HealthStatus: string
{
    case Ok = 'ok';
    case Degraded = 'degraded';
    case Failed = 'failed';

    public function isWorseThan(self $other): bool
    {
        return $this->severity() > $other->severity();
    }

    /**
     * A degraded application is still able to answer, so it stays in rotation
     * and is alerted on instead.
     */
    public function httpStatus(): int
    {
        return $this === self::Failed
            ? Response::HTTP_SERVICE_UNAVAILABLE
            : Response::HTTP_OK;
    }

    private function severity(): int
    {
        return match ($this) {
            self::Ok => 0,
            self::Degraded => 1,
            self::Failed => 2,
        };
    }
}
