<?php

namespace App\Http\Requests\Api\V1\Concerns;

use App\Models\Branch;
use Illuminate\Http\Response;

/**
 * Turns the `branch` parameter into the office a request answers for.
 *
 * One implementation so the books, the sales floor and the activity ledgers
 * cannot drift apart on who may see what: an agent gets their own branch and
 * nothing else, and only an administrator may name another office or ask for
 * every branch at once.
 */
trait ResolvesBranchScope
{
    /** Value of the `branch` parameter that asks for every branch at once. */
    public const GROUP = 'group';

    /**
     * Null means every branch. Callers must treat that as deliberate, because
     * the `forBranch` scopes read a null branch as "do not filter".
     */
    protected function resolveBranchScope(): ?Branch
    {
        $requested = $this->string('branch')->toString();

        if ($requested === '') {
            $branch = $this->user()->activeBranch();

            /**
             * Without this an account with no branch would fall through to a
             * null scope, which `Sale::forBranch()` and `Payment::forBranch()`
             * read as "every branch" - the widest possible answer to the
             * narrowest possible account.
             */
            abort_if(
                $branch === null,
                Response::HTTP_FORBIDDEN,
                'Your account has not been assigned to a branch.',
            );

            return $branch;
        }

        if (strtolower($requested) === self::GROUP) {
            abort_unless(
                $this->user()->isAdmin(),
                Response::HTTP_FORBIDDEN,
                $this->groupRefusalMessage(),
            );

            return null;
        }

        $branch = Branch::query()->where('code', strtoupper($requested))->first();

        abort_unless(
            $branch !== null && $this->user()->canWorkFrom($branch),
            Response::HTTP_FORBIDDEN,
            $this->branchRefusalMessage(),
        );

        return $branch;
    }

    protected function groupRefusalMessage(): string
    {
        return 'Consolidated views are for administrators only.';
    }

    protected function branchRefusalMessage(): string
    {
        return 'You can only read your own branch.';
    }
}
