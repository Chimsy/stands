<?php

use App\Actions\PostJournalEntry;
use App\Exceptions\AccountingException;
use App\Models\Account;
use App\Models\Branch;
use App\Models\JournalEntry;
use App\Support\LedgerLine;
use Database\Seeders\ChartOfAccountsSeeder;
use Illuminate\Support\Carbon;

beforeEach(function () {
    $this->seed(ChartOfAccountsSeeder::class);
    $this->branch = Branch::factory()->create(['code' => 'HRE']);
    $this->post = app(PostJournalEntry::class);
});

it('writes a balanced entry with both its lines', function () {
    $entry = $this->post->handle(
        branch: $this->branch,
        date: Carbon::parse('2026-03-01'),
        description: 'Cash received',
        lines: [
            LedgerLine::debit(Account::BANK, 150_00),
            LedgerLine::credit(Account::ACCOUNTS_RECEIVABLE, 150_00),
        ],
    );

    expect($entry->lines)->toHaveCount(2)
        ->and($entry->lines->sum('debit_cents'))->toBe(15000)
        ->and($entry->lines->sum('credit_cents'))->toBe(15000)
        ->and($entry->entry_date->toDateString())->toBe('2026-03-01');
});

it('rejects an entry whose debits and credits disagree', function () {
    $post = fn () => $this->post->handle(
        branch: $this->branch,
        date: Carbon::today(),
        description: 'Wrong',
        lines: [
            LedgerLine::debit(Account::BANK, 150_00),
            LedgerLine::credit(Account::ACCOUNTS_RECEIVABLE, 140_00),
        ],
    );

    expect($post)->toThrow(AccountingException::class, 'Journal entry does not balance: debits 150.00, credits 140.00.');
});

it('writes nothing when the entry is refused', function () {
    try {
        $this->post->handle(
            branch: $this->branch,
            date: Carbon::today(),
            description: 'Wrong',
            lines: [
                LedgerLine::debit(Account::BANK, 150_00),
                LedgerLine::credit(Account::ACCOUNTS_RECEIVABLE, 140_00),
            ],
        );
    } catch (AccountingException) {
        // Expected; the point of the test is what did not get written.
    }

    expect(JournalEntry::count())->toBe(0);
});

it('rejects a one-sided entry', function () {
    $post = fn () => $this->post->handle(
        branch: $this->branch,
        date: Carbon::today(),
        description: 'Half an entry',
        lines: [LedgerLine::debit(Account::BANK, 150_00)],
    );

    expect($post)->toThrow(AccountingException::class, 'A journal entry needs at least two lines.');
});

it('rejects an entry that moves nothing', function () {
    $post = fn () => $this->post->handle(
        branch: $this->branch,
        date: Carbon::today(),
        description: 'Nothing happened',
        lines: [
            LedgerLine::debit(Account::BANK, 0),
            LedgerLine::credit(Account::ACCOUNTS_RECEIVABLE, 0),
        ],
    );

    expect($post)->toThrow(AccountingException::class, 'A journal entry must move a non-zero amount.');
});

it('numbers references sequentially within a branch', function () {
    $other = Branch::factory()->create(['code' => 'BYO']);

    $lines = [
        LedgerLine::debit(Account::BANK, 100_00),
        LedgerLine::credit(Account::ACCOUNTS_RECEIVABLE, 100_00),
    ];

    $first = $this->post->handle($this->branch, Carbon::today(), 'One', $lines);
    $second = $this->post->handle($this->branch, Carbon::today(), 'Two', $lines);
    $elsewhere = $this->post->handle($other, Carbon::today(), 'Three', $lines);

    expect($first->reference)->toBe('JE-HRE-000001')
        ->and($second->reference)->toBe('JE-HRE-000002')
        ->and($elsewhere->reference)->toBe('JE-BYO-000001');
});
