<?php

use App\Support\Health\Checks\CacheCheck;
use App\Support\Health\Checks\ChartOfAccountsCheck;
use App\Support\Health\Checks\DatabaseCheck;
use App\Support\Health\Checks\LedgerCheck;
use App\Support\Health\Checks\MigrationsCheck;
use App\Support\Health\Checks\QueueCheck;
use App\Support\Health\Checks\StorageCheck;

return [

    /*
    |--------------------------------------------------------------------------
    | Monitoring Secret
    |--------------------------------------------------------------------------
    |
    | The diagnostics endpoint reports how the deployment is put together, so it
    | is never public. An administrator's API token opens it; so does this
    | secret in the X-Health-Token header, which is how an uptime monitor gets
    | in without holding a user's credentials. Leave it unset to allow
    | administrators only.
    |
    */

    'token' => env('HEALTH_CHECK_TOKEN'),

    /*
    |--------------------------------------------------------------------------
    | Released Version
    |--------------------------------------------------------------------------
    |
    | Echoed back by every health endpoint so a deploy can be confirmed from
    | outside. Set it to the release tag or commit the instance was built from.
    |
    */

    'version' => env('APP_VERSION', 'dev'),

    /*
    |--------------------------------------------------------------------------
    | Checks
    |--------------------------------------------------------------------------
    |
    | Resolved from the container in this order. The ones that report themselves
    | as critical decide readiness and are polled by the load balancer, so keep
    | that set cheap; the rest are run by the diagnostics endpoint only.
    |
    */

    'checks' => [
        DatabaseCheck::class,
        MigrationsCheck::class,
        CacheCheck::class,
        StorageCheck::class,
        ChartOfAccountsCheck::class,
        QueueCheck::class,
        LedgerCheck::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Thresholds
    |--------------------------------------------------------------------------
    |
    | Where "working" stops and "working, but look at it" begins. A check that
    | answers correctly but takes longer than slow_ms is reported as degraded
    | rather than healthy - it still serves traffic, and it still gets noticed.
    |
    */

    'thresholds' => [
        'slow_ms' => (float) env('HEALTH_SLOW_MS', 500),
        'queue_backlog' => (int) env('HEALTH_MAX_QUEUE_BACKLOG', 250),
        'failed_jobs' => (int) env('HEALTH_MAX_FAILED_JOBS', 10),
    ],

    /*
    |--------------------------------------------------------------------------
    | Rate Limit
    |--------------------------------------------------------------------------
    |
    | Requests per minute per address for the readiness and diagnostics
    | endpoints, which both touch the database. Liveness is never throttled: it
    | reads nothing, and a monitor must be able to tell "up" from "throttled".
    | Set to 0 to lift the limit.
    |
    */

    'rate_limit' => (int) env('HEALTH_RATE_LIMIT', 60),

];
