<?php

namespace App\Http\Middleware;

use App\Models\Branch;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Settles which branch the request is worked from before anything queries.
 *
 * Branch is the tenant dimension, so every scoped query reads it from one
 * place. An agent has no choice in the matter; an administrator names the
 * office in `X-Branch` and the whole request - stands, sales, receipts,
 * statements - answers for it.
 */
class ResolveActiveBranch
{
    /** Header the front-end sends the administrator's current office in. */
    public const HEADER = 'X-Branch';

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $requested = strtoupper(trim((string) $request->header(self::HEADER)));

        if ($user === null || $requested === '') {
            return $next($request);
        }

        $branch = Branch::query()->where('code', $requested)->first();

        /**
         * Asking for an office you are not entitled to is refused rather than
         * quietly ignored: silently answering for a different branch than the
         * caller believes they are in would misfile a sale.
         */
        abort_if($branch === null, Response::HTTP_FORBIDDEN, 'There is no branch with that code.');
        abort_unless($user->canWorkFrom($branch), Response::HTTP_FORBIDDEN, 'You are not entitled to work from that branch.');

        $user->workFrom($branch);

        return $next($request);
    }
}
