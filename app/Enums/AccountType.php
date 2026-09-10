<?php

namespace App\Enums;

enum AccountType: string
{
    case Asset = 'asset';
    case Liability = 'liability';
    case Equity = 'equity';
    case Revenue = 'revenue';
    case Expense = 'expense';

    /**
     * The side an account of this type increases on. Assets and expenses carry
     * debit balances; everything else carries a credit balance.
     */
    public function normalBalance(): BalanceSide
    {
        return match ($this) {
            self::Asset, self::Expense => BalanceSide::Debit,
            self::Liability, self::Equity, self::Revenue => BalanceSide::Credit,
        };
    }

    /** Whether the account appears on the balance sheet rather than the income statement. */
    public function isBalanceSheet(): bool
    {
        return in_array($this, [self::Asset, self::Liability, self::Equity], true);
    }
}
