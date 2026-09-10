<?php

namespace Database\Seeders;

use App\Enums\AccountType;
use App\Models\Account;
use Illuminate\Database\Seeder;

/**
 * The chart of accounts, shared by every branch.
 *
 * It is deliberately small: this business buys serviced land, sells stands, and
 * collects the money. Anything the posting rules in App\Actions do not use has
 * been left out rather than carried as an empty account.
 */
class ChartOfAccountsSeeder extends Seeder
{
    /**
     * @var list<array{code: string, name: string, type: AccountType, position: int}>
     */
    private const ACCOUNTS = [
        ['code' => Account::BANK, 'name' => 'Bank', 'type' => AccountType::Asset, 'position' => 10],
        ['code' => Account::ACCOUNTS_RECEIVABLE, 'name' => 'Accounts Receivable', 'type' => AccountType::Asset, 'position' => 20],
        ['code' => Account::LAND_INVENTORY, 'name' => 'Land Inventory', 'type' => AccountType::Asset, 'position' => 30],
        ['code' => Account::SHARE_CAPITAL, 'name' => 'Share Capital', 'type' => AccountType::Equity, 'position' => 40],
        ['code' => Account::STAND_SALES_REVENUE, 'name' => 'Stand Sales Revenue', 'type' => AccountType::Revenue, 'position' => 50],
        ['code' => Account::COST_OF_SALES, 'name' => 'Cost of Sales', 'type' => AccountType::Expense, 'position' => 60],
    ];

    public function run(): void
    {
        foreach (self::ACCOUNTS as $account) {
            Account::updateOrCreate(['code' => $account['code']], $account);
        }
    }
}
