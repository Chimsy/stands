<?php

namespace App\Support;

/**
 * One side of a journal entry, before it is written.
 *
 * The constructor is private and both factories zero the opposite side, so a
 * line carrying a debit and a credit at once cannot be built. A credit is
 * expressed by which factory you call, never by a negative amount.
 */
final readonly class LedgerLine
{
    private function __construct(
        public string $accountCode,
        public int $debitCents,
        public int $creditCents,
    ) {}

    public static function debit(string $accountCode, int $cents): self
    {
        return new self($accountCode, abs($cents), 0);
    }

    public static function credit(string $accountCode, int $cents): self
    {
        return new self($accountCode, 0, abs($cents));
    }
}
