<?php

namespace App\Enums;

/**
 * What a signed-in account is allowed to do.
 *
 * The two levels differ in reach, not in capability: an agent works one branch,
 * an administrator works any of them. Neither can post to the ledger by hand.
 */
enum UserRole: string
{
    /** An agent. Sees and sells only the stock of the branch they belong to. */
    case Sales = 'sales';

    /** Head office. May work from any branch and read the group dashboard. */
    case Admin = 'admin';

    public function label(): string
    {
        return match ($this) {
            self::Sales => 'Sales agent',
            self::Admin => 'Administrator',
        };
    }
}
