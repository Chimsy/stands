<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Guards the diagnostics endpoint, which names the drivers, versions and table
 * sizes behind the API - a map of the deployment that no anonymous caller
 * should be handed.
 *
 * Two ways in, because the two callers are different: an administrator reading
 * it in the browser has an API token, and an uptime monitor polling it at three
 * in the morning has no user at all. The monitor presents the shared secret
 * from `config/health.php` instead.
 */
class EnsureHealthInspector
{
    /** Header an unattended monitor presents the shared secret in. */
    public const HEADER = 'X-Health-Token';

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($this->presentsSecret($request)) {
            return $next($request);
        }

        $user = Auth::guard('sanctum')->user();

        abort_if($user === null, Response::HTTP_UNAUTHORIZED, 'Health diagnostics require a token.');
        abort_unless($user->isAdmin(), Response::HTTP_FORBIDDEN, 'This is available to administrators only.');

        return $next($request);
    }

    /**
     * Compared in constant time, and only when a secret has actually been
     * configured: an unset HEALTH_CHECK_TOKEN must not mean "any token opens
     * it", or an empty header would be the key to the deployment.
     */
    private function presentsSecret(Request $request): bool
    {
        $expected = (string) config('health.token');
        $presented = (string) $request->header(self::HEADER, '');

        return $expected !== '' && $presented !== '' && hash_equals($expected, $presented);
    }
}
