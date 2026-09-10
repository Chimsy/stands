<?php

namespace App\Reports;

use App\Models\Branch;
use App\Models\Instalment;
use Illuminate\Support\Carbon;

/**
 * What is still owed on payment plans, split by how overdue each instalment is.
 *
 * The ledger says how large the receivable is in total; this says how much of
 * it is late, which the balance sheet alone cannot tell you.
 */
class ReceivablesAgeing
{
    /**
     * Upper bound of each overdue bucket, in days. Anything past the last one
     * falls into the final bucket.
     *
     * @var array<string, ?int>
     */
    private const BUCKETS = [
        'notYetDue' => null,
        'days1To30' => 30,
        'days31To60' => 60,
        'days61To90' => 90,
        'over90Days' => null,
    ];

    /**
     * @return array{
     *     asAt: string,
     *     branch: ?string,
     *     buckets: array<string, int>,
     *     totalCents: int,
     *     overdueCents: int
     * }
     */
    public function handle(?Branch $branch, Carbon $asAt): array
    {
        $buckets = array_fill_keys(array_keys(self::BUCKETS), 0);

        Instalment::query()
            ->unsettled()
            ->whereHas('sale', fn ($sale) => $sale->forBranch($branch))
            /** lazyById needs the key in the result, so it is selected alongside the amounts. */
            ->select(['id', 'due_date', 'amount_cents', 'paid_cents'])
            ->lazyById(1000)
            ->each(function (Instalment $instalment) use (&$buckets, $asAt): void {
                $buckets[$this->bucketFor($instalment->due_date, $asAt)] += $instalment->outstanding_cents;
            });

        $total = array_sum($buckets);

        return [
            'asAt' => $asAt->toDateString(),
            'branch' => $branch?->code,
            'buckets' => $buckets,
            'totalCents' => $total,
            'overdueCents' => $total - $buckets['notYetDue'],
        ];
    }

    private function bucketFor(Carbon $dueDate, Carbon $asAt): string
    {
        if ($dueDate->greaterThan($asAt)) {
            return 'notYetDue';
        }

        $daysLate = $dueDate->diffInDays($asAt);

        return match (true) {
            $daysLate <= 30 => 'days1To30',
            $daysLate <= 60 => 'days31To60',
            $daysLate <= 90 => 'days61To90',
            default => 'over90Days',
        };
    }
}
