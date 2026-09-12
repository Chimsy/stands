<?php

namespace App\Support\Health\Checks;

use App\Models\Account;
use App\Support\Health\CheckResult;
use App\Support\Health\HealthCheck;
use Throwable;

/**
 * The accounts the posting rules name by code.
 *
 * `PostJournalEntry` looks each one up by code as it writes, so a chart that is
 * missing one of them does not degrade anything - it takes down selling and
 * receipting entirely, and only at the moment somebody tries. A migrated but
 * unseeded database is the usual cause, and it is worth catching at deploy.
 */
final class ChartOfAccountsCheck implements HealthCheck
{
    /** @var list<string> */
    private const REQUIRED = [
        Account::BANK,
        Account::ACCOUNTS_RECEIVABLE,
        Account::LAND_INVENTORY,
        Account::SHARE_CAPITAL,
        Account::STAND_SALES_REVENUE,
        Account::COST_OF_SALES,
    ];

    public function name(): string
    {
        return 'chart-of-accounts';
    }

    public function isCritical(): bool
    {
        return true;
    }

    public function run(): CheckResult
    {
        try {
            $present = Account::query()->whereIn('code', self::REQUIRED)->pluck('code')->all();
        } catch (Throwable $exception) {
            return CheckResult::failed($this->name(), 'The chart of accounts could not be read.', [
                'error' => $exception->getMessage(),
            ]);
        }

        $missing = array_values(array_diff(self::REQUIRED, $present));

        if ($missing !== []) {
            return CheckResult::failed(
                $this->name(),
                sprintf('%d account(s) the posting rules require are missing.', count($missing)),
                ['missing' => $missing, 'required' => self::REQUIRED],
            );
        }

        return CheckResult::ok($this->name(), 'Every account the posting rules need is present.', [
            'required' => count(self::REQUIRED),
        ]);
    }
}
