<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * Resolves route bindings only within the signed-in user's branch.
 *
 * A record belonging to another branch is reported as missing rather than
 * forbidden: a 403 would confirm that the reference exists, which is enough to
 * enumerate another branch's stands and sales.
 *
 * The model must define a `forBranch` scope.
 */
trait ScopedToUserBranch
{
    public function resolveRouteBinding($value, $field = null): ?Model
    {
        return $this->newQuery()
            ->forBranch(Auth::user()?->branch)
            ->where($field ?? $this->getRouteKeyName(), $value)
            ->first();
    }
}
