<?php

use App\Actions\PostJournalEntry;
use App\Models\Account;
use App\Models\Branch;
use App\Support\LedgerLine;
use Database\Seeders\ChartOfAccountsSeeder;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->seed(ChartOfAccountsSeeder::class);
    Carbon::setTestNow('2026-06-15');

    $this->harare = Branch::factory()->create(['code' => 'HRE']);
    $this->bulawayo = Branch::factory()->create(['code' => 'BYO']);
    $this->agent = agentAt($this->harare);

    $open = fn (Branch $branch, int $cents) => app(PostJournalEntry::class)->handle(
        branch: $branch,
        date: Carbon::parse('2026-01-01'),
        description: 'Opening inventory',
        lines: [
            LedgerLine::debit(Account::LAND_INVENTORY, $cents),
            LedgerLine::credit(Account::SHARE_CAPITAL, $cents),
        ],
    );

    $open($this->harare, 20_000_00);
    $open($this->bulawayo, 9_000_00);
});

afterEach(fn () => Carbon::setTestNow());

it('returns 401 without a token', function (string $route) {
    $this->getJson(route($route))->assertUnauthorized();
})->with([
    'api.v1.reports.trial-balance',
    'api.v1.reports.income-statement',
    'api.v1.reports.balance-sheet',
    'api.v1.reports.receivables-ageing',
]);

it('defaults to the caller\'s own branch', function () {
    Sanctum::actingAs($this->agent);

    $this->getJson(route('api.v1.reports.balance-sheet'))
        ->assertOk()
        ->assertJsonPath('data.branch', 'HRE')
        ->assertJsonPath('data.assetsCents', 2_000_000)
        ->assertJsonPath('data.inBalance', true);
});

it('consolidates every branch when asked for the group', function () {
    Sanctum::actingAs($this->agent);

    $this->getJson(route('api.v1.reports.balance-sheet', ['branch' => 'group']))
        ->assertOk()
        ->assertJsonPath('data.branch', null)
        ->assertJsonPath('data.assetsCents', 2_900_000)
        ->assertJsonPath('data.inBalance', true);
});

it('refuses to show another branch\'s books', function (string $route) {
    Sanctum::actingAs($this->agent);

    $this->getJson(route($route, ['branch' => 'BYO']))
        ->assertForbidden()
        ->assertJsonPath('message', 'You can only read your own branch, or the consolidated group.');
})->with([
    'api.v1.reports.trial-balance',
    'api.v1.reports.balance-sheet',
]);

it('accepts the caller\'s own branch code', function () {
    Sanctum::actingAs($this->agent);

    $this->getJson(route('api.v1.reports.trial-balance', ['branch' => 'hre']))
        ->assertOk()
        ->assertJsonPath('data.branch', 'HRE');
});

it('cuts the trial balance off at the requested date', function () {
    Sanctum::actingAs($this->agent);

    $this->getJson(route('api.v1.reports.trial-balance', ['to' => '2025-12-31']))
        ->assertOk()
        ->assertJsonPath('data.asAt', '2025-12-31')
        ->assertJsonPath('data.totalDebitCents', 0)
        ->assertJsonPath('data.inBalance', true);
});

it('reports the income statement for the year to date by default', function () {
    Sanctum::actingAs($this->agent);

    $this->getJson(route('api.v1.reports.income-statement'))
        ->assertOk()
        ->assertJsonPath('data.from', '2026-01-01')
        ->assertJsonPath('data.to', '2026-06-15');
});
