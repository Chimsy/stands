<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Raised when an operation would put the books into a state that cannot be
 * true - an entry that does not balance, a stand sold twice, money received
 * against a debt that is already settled.
 *
 * These are programming or workflow errors rather than user input errors, so
 * they surface as 422s through the API rather than being caught and ignored.
 */
class AccountingException extends RuntimeException
{
    public static function unbalanced(int $debitCents, int $creditCents): self
    {
        return new self(sprintf(
            'Journal entry does not balance: debits %s, credits %s.',
            number_format($debitCents / 100, 2),
            number_format($creditCents / 100, 2),
        ));
    }

    public static function emptyEntry(): self
    {
        return new self('A journal entry needs at least two lines.');
    }

    public static function zeroValueEntry(): self
    {
        return new self('A journal entry must move a non-zero amount.');
    }
}
