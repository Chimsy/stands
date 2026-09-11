<?php

namespace Database\Seeders;

use App\Actions\PostJournalEntry;
use App\Actions\RecordPayment;
use App\Actions\SellStand;
use App\Enums\PaymentMethod;
use App\Enums\SaleType;
use App\Enums\StandStatus;
use App\Enums\UserRole;
use App\Models\Account;
use App\Models\Branch;
use App\Models\Buyer;
use App\Models\Stand;
use App\Models\User;
use App\Support\LedgerLine;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Gives each branch a trading history, so the statements have something to say
 * the moment the app is opened.
 *
 * Every sale and receipt goes through the same actions the API uses, so the
 * seeded ledger is produced the same way a real one would be rather than being
 * written straight into the journal.
 *
 * The fixture's sold and in-progress markers are the script for which stands
 * traded. Each of those stands is put back to available and then actually sold,
 * so its status ends up being something the books can account for.
 */
class TradingHistorySeeder extends Seeder
{
    private const BUYERS_PER_BRANCH = 90;

    private const INSTALMENT_TERMS = [12, 18, 24, 36];

    private const DEPOSIT_PERCENTAGES = [10, 15, 20, 25, 30];

    /** Roughly one plan in six is behind on its instalments, so arrears reporting has something to show. */
    private const ARREARS_IN = 6;

    public function __construct(
        private readonly SellStand $sellStand,
        private readonly RecordPayment $recordPayment,
        private readonly PostJournalEntry $postJournalEntry,
    ) {}

    public function run(): void
    {
        /** Deterministic, so re-seeding reproduces the same books. */
        fake()->seed(20260910);
        mt_srand(20260910);

        foreach (Branch::query()->with('sitePlans')->get() as $branch) {
            $this->openBooks($branch);
            $this->trade($branch, $this->buyersFor($branch));
        }
    }

    /**
     * The land the business already owns, brought onto the books before any of
     * it is sold. Without this, selling a stand would credit an inventory
     * account that had never been debited.
     */
    private function openBooks(Branch $branch): void
    {
        $inventoryCents = (int) Stand::query()->forBranch($branch)->sum('cost_cents');

        if ($inventoryCents === 0) {
            return;
        }

        $this->postJournalEntry->handle(
            branch: $branch,
            date: Carbon::today()->subYears(2),
            description: 'Opening balance - serviced land brought into inventory',
            lines: [
                LedgerLine::debit(Account::LAND_INVENTORY, $inventoryCents),
                LedgerLine::credit(Account::SHARE_CAPITAL, $inventoryCents),
            ],
        );
    }

    /**
     * @return Collection<int, Buyer>
     */
    private function buyersFor(Branch $branch): Collection
    {
        return Buyer::factory()
            ->count(self::BUYERS_PER_BRANCH)
            ->create(['branch_id' => $branch->id]);
    }

    /**
     * @param  Collection<int, Buyer>  $buyers
     */
    private function trade(Branch $branch, Collection $buyers): void
    {
        /** Attributed to an agent, never to an administrator: the sales floor booked these. */
        $agent = User::query()
            ->where('branch_id', $branch->id)
            ->where('role', UserRole::Sales)
            ->first();

        $traded = Stand::query()
            ->forBranch($branch)
            ->where('status', '!=', StandStatus::Available)
            ->orderBy('stand_number')
            ->get();

        DB::transaction(function () use ($traded, $buyers, $agent): void {
            foreach ($traded as $stand) {
                $onPlan = $stand->status === StandStatus::InProgress;

                $stand->update(['status' => StandStatus::Available]);

                $saleDate = Carbon::today()->subDays(mt_rand(20, 540));
                $buyer = $buyers->random();

                if (! $onPlan) {
                    $this->sellStand->handle(
                        stand: $stand,
                        buyer: $buyer,
                        type: SaleType::Cash,
                        priceCents: $stand->price_cents,
                        saleDate: $saleDate,
                        method: fake()->randomElement(PaymentMethod::cases()),
                        soldBy: $agent,
                    );

                    continue;
                }

                $this->sellOnPlan($stand, $buyer, $agent, $saleDate);
            }
        });
    }

    private function sellOnPlan(Stand $stand, Buyer $buyer, ?User $agent, Carbon $saleDate): void
    {
        $term = fake()->randomElement(self::INSTALMENT_TERMS);
        $depositCents = (int) round($stand->price_cents * fake()->randomElement(self::DEPOSIT_PERCENTAGES) / 100);

        $sale = $this->sellStand->handle(
            stand: $stand,
            buyer: $buyer,
            type: SaleType::PaymentPlan,
            priceCents: $stand->price_cents,
            saleDate: $saleDate,
            method: fake()->randomElement(PaymentMethod::cases()),
            soldBy: $agent,
            depositCents: $depositCents,
            instalmentCount: $term,
        );

        /** Some buyers are behind, so the last instalment or two stays unpaid. */
        $skip = mt_rand(1, self::ARREARS_IN) === 1 ? mt_rand(1, 2) : 0;

        $due = $sale->instalments()
            ->where('due_date', '<=', Carbon::today())
            ->orderBy('sequence')
            ->get();

        foreach ($due->take(max(0, $due->count() - $skip)) as $instalment) {
            $this->recordPayment->handle(
                sale: $sale,
                amountCents: $instalment->amount_cents,
                method: fake()->randomElement(PaymentMethod::cases()),
                paidOn: $instalment->due_date,
                receivedBy: $agent,
            );
        }
    }
}
