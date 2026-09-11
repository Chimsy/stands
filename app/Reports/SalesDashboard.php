<?php

namespace App\Reports;

use App\Enums\SaleType;
use App\Enums\StandStatus;
use App\Models\Branch;
use App\Models\Payment;
use App\Models\Sale;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * How the stands are selling, branch by branch.
 *
 * The statements answer what the books say; this answers what the sales floor
 * did - how much stock is left, what was signed in the period, and how much of
 * it has actually been collected. It reads the operational tables rather than
 * the journal, so a figure here is a count of stands and sales, not a balance.
 *
 * Scoping one branch or consolidating the group is the same code path: a null
 * branch simply widens every query, exactly as the statements do.
 */
class SalesDashboard
{
    /** How many agents the leaderboard shows. */
    private const TOP_AGENTS = 6;

    /** How many of the latest sales the dashboard lists. */
    private const RECENT_SALES = 8;

    /**
     * @return array{
     *     from: string,
     *     to: string,
     *     branch: ?string,
     *     totals: array<string, int>,
     *     branches: list<array<string, mixed>>,
     *     monthly: list<array{month: string, salesCount: int, valueCents: int, collectedCents: int}>,
     *     mix: list<array{type: string, salesCount: int, valueCents: int}>,
     *     topAgents: list<array{name: string, branch: ?string, salesCount: int, valueCents: int}>,
     *     recentSales: list<array<string, mixed>>
     * }
     */
    public function handle(?Branch $branch, Carbon $from, Carbon $to): array
    {
        $branches = Branch::query()
            ->when($branch, fn ($query) => $query->whereKey($branch->id))
            ->orderBy('name')
            ->get();

        $stock = $this->stockByBranch($branch);
        $signed = $this->signedInPeriod($branch, $from, $to);
        $collected = $this->collectedInPeriod($branch, $from, $to);
        $book = $this->bookToDate($branch, $to);

        $rows = $branches->map(fn (Branch $office): array => [
            'code' => $office->code,
            'name' => $office->name,
            'city' => $office->city,
            'stands' => $this->standCounts($stock->get($office->id)),
            'salesCount' => (int) ($signed[$office->id]['count'] ?? 0),
            'valueCents' => (int) ($signed[$office->id]['value'] ?? 0),
            'costCents' => (int) ($signed[$office->id]['cost'] ?? 0),
            'collectedCents' => (int) ($collected[$office->id] ?? 0),
            'outstandingCents' => (int) ($book[$office->id] ?? 0),
        ])->all();

        return [
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'branch' => $branch?->code,
            'totals' => $this->totals($rows),
            'branches' => $rows,
            'monthly' => $this->monthly($branch, $from, $to),
            'mix' => $this->mix($branch, $from, $to),
            'topAgents' => $this->topAgents($branch, $from, $to),
            'recentSales' => $this->recentSales($branch),
        ];
    }

    /**
     * Narrows a query to an inclusive date range, correctly on either engine.
     *
     * `whereBetween($column, [$from, $to])` silently drops the last day on
     * SQLite: a `date` column is stored there as the text "2026-09-11 00:00:00",
     * which sorts after the bare "2026-09-11" upper bound. Half-open against the
     * following morning is true on both engines, and unlike `whereDate()` it
     * leaves the column bare, so the (branch_id, date) indexes are still used.
     *
     * @param  Builder<covariant Model>  $query
     */
    private function withinPeriod(Builder $query, string $column, Carbon $from, Carbon $to): void
    {
        $query->where($column, '>=', $from->toDateString())
            ->where($column, '<', $to->copy()->addDay()->toDateString());
    }

    /**
     * Stands per branch per status. Stock belongs to a site plan rather than
     * directly to a branch, so the plan is what carries the branch here.
     *
     * @return Collection<int, Collection<int, object>>
     */
    private function stockByBranch(?Branch $branch): Collection
    {
        return DB::table('stands')
            ->join('site_plans', 'site_plans.id', '=', 'stands.site_plan_id')
            ->when($branch, fn ($query) => $query->where('site_plans.branch_id', '=', $branch->id))
            ->groupBy('site_plans.branch_id', 'stands.status')
            ->selectRaw('site_plans.branch_id AS branch_id, stands.status AS status, COUNT(*) AS total')
            ->get()
            ->groupBy('branch_id');
    }

    /**
     * @param  ?Collection<int, object>  $rows
     * @return array<string, int>
     */
    private function standCounts(?Collection $rows): array
    {
        $counts = ['total' => 0];

        foreach (StandStatus::cases() as $status) {
            $counts[$status->value] = (int) ($rows?->firstWhere('status', $status->value)?->total ?? 0);
            $counts['total'] += $counts[$status->value];
        }

        return $counts;
    }

    /**
     * Value signed in the period, per branch.
     *
     * @return array<int, array{count: int, value: int, cost: int}>
     */
    private function signedInPeriod(?Branch $branch, Carbon $from, Carbon $to): array
    {
        return Sale::query()
            ->forBranch($branch)
            ->tap(fn ($query) => $this->withinPeriod($query, 'sale_date', $from, $to))
            ->groupBy('branch_id')
            ->selectRaw('branch_id, COUNT(*) AS sales_count, SUM(price_cents) AS value_cents, SUM(cost_cents) AS cost_cents')
            ->get()
            ->mapWithKeys(fn ($row): array => [(int) $row->branch_id => [
                'count' => (int) $row->sales_count,
                'value' => (int) $row->value_cents,
                'cost' => (int) $row->cost_cents,
            ]])
            ->all();
    }

    /**
     * @return array<int, int>
     */
    private function collectedInPeriod(?Branch $branch, Carbon $from, Carbon $to): array
    {
        return Payment::query()
            ->forBranch($branch)
            ->tap(fn ($query) => $this->withinPeriod($query, 'paid_on', $from, $to))
            ->groupBy('branch_id')
            ->selectRaw('branch_id, SUM(amount_cents) AS collected_cents')
            ->pluck('collected_cents', 'branch_id')
            ->map(fn ($cents): int => (int) $cents)
            ->all();
    }

    /**
     * What each branch is still owed as at `$to`: everything ever signed less
     * everything ever received. Deliberately not restricted to the period, so
     * it reconciles with Accounts Receivable on the balance sheet.
     *
     * @return array<int, int>
     */
    private function bookToDate(?Branch $branch, Carbon $to): array
    {
        $signed = Sale::query()
            ->forBranch($branch)
            ->whereDate('sale_date', '<=', $to->toDateString())
            ->groupBy('branch_id')
            ->selectRaw('branch_id, SUM(price_cents) AS total_cents')
            ->pluck('total_cents', 'branch_id');

        $received = Payment::query()
            ->forBranch($branch)
            ->whereDate('paid_on', '<=', $to->toDateString())
            ->groupBy('branch_id')
            ->selectRaw('branch_id, SUM(amount_cents) AS total_cents')
            ->pluck('total_cents', 'branch_id');

        return $signed
            ->map(fn ($cents, $branchId): int => max(0, (int) $cents - (int) ($received[$branchId] ?? 0)))
            ->all();
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array<string, int>
     */
    private function totals(array $rows): array
    {
        $sum = fn (string $key): int => (int) array_sum(array_column($rows, $key));

        $stands = array_column($rows, 'stands');
        $standTotal = fn (string $key): int => (int) array_sum(array_column($stands, $key));

        return [
            'standsTotal' => $standTotal('total'),
            'standsAvailable' => $standTotal(StandStatus::Available->value),
            'standsSold' => $standTotal(StandStatus::Sold->value),
            'standsInProgress' => $standTotal(StandStatus::InProgress->value),
            'salesCount' => $sum('salesCount'),
            'valueCents' => $sum('valueCents'),
            'costCents' => $sum('costCents'),
            'grossProfitCents' => $sum('valueCents') - $sum('costCents'),
            'collectedCents' => $sum('collectedCents'),
            'outstandingCents' => $sum('outstandingCents'),
        ];
    }

    /**
     * Signings and collections month by month across the period.
     *
     * Every month between the bounds is emitted, including the empty ones, so a
     * chart drawn from this has no gaps to interpolate over. The grouping is
     * done in PHP rather than with a date function, which differs between
     * database engines.
     *
     * @return list<array{month: string, salesCount: int, valueCents: int, collectedCents: int}>
     */
    private function monthly(?Branch $branch, Carbon $from, Carbon $to): array
    {
        $months = [];

        for ($month = $from->copy()->startOfMonth(); $month->lessThanOrEqualTo($to); $month->addMonth()) {
            $months[$month->format('Y-m')] = ['salesCount' => 0, 'valueCents' => 0, 'collectedCents' => 0];
        }

        Sale::query()
            ->forBranch($branch)
            ->tap(fn ($query) => $this->withinPeriod($query, 'sale_date', $from, $to))
            ->select(['id', 'sale_date', 'price_cents'])
            ->lazyById(1000)
            ->each(function (Sale $sale) use (&$months): void {
                $key = $sale->sale_date->format('Y-m');

                if (isset($months[$key])) {
                    $months[$key]['salesCount']++;
                    $months[$key]['valueCents'] += $sale->price_cents;
                }
            });

        Payment::query()
            ->forBranch($branch)
            ->tap(fn ($query) => $this->withinPeriod($query, 'paid_on', $from, $to))
            ->select(['id', 'paid_on', 'amount_cents'])
            ->lazyById(1000)
            ->each(function (Payment $payment) use (&$months): void {
                $key = $payment->paid_on->format('Y-m');

                if (isset($months[$key])) {
                    $months[$key]['collectedCents'] += $payment->amount_cents;
                }
            });

        return collect($months)
            ->map(fn (array $totals, string $month): array => ['month' => $month, ...$totals])
            ->values()
            ->all();
    }

    /**
     * Cash against payment plan, which is what says how much of the book will
     * arrive as instalments rather than on the day.
     *
     * @return list<array{type: string, salesCount: int, valueCents: int}>
     */
    private function mix(?Branch $branch, Carbon $from, Carbon $to): array
    {
        $signed = Sale::query()
            ->forBranch($branch)
            ->tap(fn ($query) => $this->withinPeriod($query, 'sale_date', $from, $to))
            ->groupBy('type')
            ->selectRaw('type, COUNT(*) AS sales_count, SUM(price_cents) AS value_cents')
            ->get()
            ->keyBy(fn ($row): string => $row->type instanceof SaleType ? $row->type->value : (string) $row->type);

        return collect(SaleType::cases())
            ->map(fn (SaleType $type): array => [
                'type' => $type->value,
                'salesCount' => (int) ($signed[$type->value]->sales_count ?? 0),
                'valueCents' => (int) ($signed[$type->value]->value_cents ?? 0),
            ])
            ->all();
    }

    /**
     * @return list<array{name: string, branch: ?string, salesCount: int, valueCents: int}>
     */
    private function topAgents(?Branch $branch, Carbon $from, Carbon $to): array
    {
        return Sale::query()
            ->forBranch($branch)
            ->tap(fn ($query) => $this->withinPeriod($query, 'sale_date', $from, $to))
            ->whereNotNull('sold_by')
            ->join('users', 'users.id', '=', 'sales.sold_by')
            ->leftJoin('branches', 'branches.id', '=', 'sales.branch_id')
            ->groupBy('users.id', 'users.name', 'branches.code')
            ->selectRaw('users.name AS name, branches.code AS branch_code, COUNT(*) AS sales_count, SUM(sales.price_cents) AS value_cents')
            ->orderByDesc('value_cents')
            ->limit(self::TOP_AGENTS)
            ->get()
            ->map(fn ($row): array => [
                'name' => $row->name,
                'branch' => $row->branch_code,
                'salesCount' => (int) $row->sales_count,
                'valueCents' => (int) $row->value_cents,
            ])
            ->all();
    }

    /**
     * The latest signings, regardless of period: a dashboard opened on the
     * first of the month should still show what happened last week.
     *
     * @return list<array<string, mixed>>
     */
    private function recentSales(?Branch $branch): array
    {
        return Sale::query()
            ->forBranch($branch)
            ->with(['stand:id,stand_number', 'buyer:id,name', 'branch:id,code,name'])
            ->withSum('payments', 'amount_cents')
            ->latest('sale_date')
            ->latest('id')
            ->limit(self::RECENT_SALES)
            ->get()
            ->map(fn (Sale $sale): array => [
                'reference' => $sale->reference,
                'saleDate' => $sale->sale_date->toDateString(),
                'standNumber' => $sale->stand?->stand_number,
                'buyerName' => $sale->buyer?->name,
                'branch' => $sale->branch?->code,
                'type' => $sale->type->value,
                'status' => $sale->status->value,
                'valueCents' => $sale->price_cents,
                'paidCents' => $sale->paid_cents,
                'outstandingCents' => $sale->outstanding_cents,
            ])
            ->all();
    }
}
