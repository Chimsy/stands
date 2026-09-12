<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\HealthStatus;
use App\Http\Controllers\Controller;
use App\Support\Health\SystemHealth;
use Illuminate\Http\JsonResponse;

/**
 * What a monitor asks the application about itself.
 *
 * Three depths, because three different things ask: a container orchestrator
 * wants to know the process is alive, a load balancer wants to know this
 * instance can serve a request, and a person wants to know what is wrong. They
 * answer 200 while the application is usable and 503 once it is not, so an
 * uptime monitor needs no rule beyond the status code.
 */
class HealthController extends Controller
{
    public function __construct(private SystemHealth $health) {}

    /**
     * Liveness: the process is up and routing requests.
     *
     * It touches no dependency at all, so it answers 200 even while the
     * database is down. That is the point: a failing dependency should not have
     * the orchestrator restart a container that is running perfectly well.
     */
    public function live(): JsonResponse
    {
        return $this->respond(HealthStatus::Ok, []);
    }

    /**
     * Readiness: this instance can serve a request right now.
     *
     * The critical checks only - database, schema, cache, storage, chart of
     * accounts - so it stays cheap enough to poll every few seconds. A 503
     * takes the instance out of rotation without stopping it.
     */
    public function ready(): JsonResponse
    {
        $report = $this->health->readiness();

        return $this->respond($report->status(), [
            'durationMs' => $report->durationMs(),
            'checks' => $report->summary(),
        ]);
    }

    /**
     * Diagnostics: everything, for whoever has to fix it.
     *
     * Adds the checks too expensive to poll - the queue backlog and the ledger
     * balance - and describes the instance itself. Administrators and the
     * monitoring secret only; see EnsureHealthInspector.
     */
    public function diagnostics(): JsonResponse
    {
        $report = $this->health->diagnostics();

        return $this->respond($report->status(), [
            'durationMs' => $report->durationMs(),
            'checks' => $report->detailed(),
            'runtime' => $this->health->runtime(),
            'footprint' => $this->health->footprint(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $body
     */
    private function respond(HealthStatus $status, array $body): JsonResponse
    {
        return response()
            ->json(['status' => $status->value, ...$this->health->identity(), ...$body], $status->httpStatus())
            /** A cached health check is a lie the next time it is read. */
            ->header('Cache-Control', 'no-store, max-age=0');
    }
}
