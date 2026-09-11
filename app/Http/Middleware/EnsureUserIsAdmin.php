<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Guards the routes only head office may read, such as the group dashboard.
 *
 * Unlike a cross-branch record, the existence of these endpoints is not a
 * secret worth keeping, so an agent gets a plain 403 rather than a 404.
 */
class EnsureUserIsAdmin
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($request->user()?->isAdmin(), Response::HTTP_FORBIDDEN, 'This is available to administrators only.');

        return $next($request);
    }
}
