<?php

namespace App\Models;

use App\Enums\AccountType;
use Database\Factories\AccountFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A line in the chart of accounts. The chart is shared across branches.
 */
#[Fillable(['code', 'name', 'type', 'position'])]
class Account extends Model
{
    /** @use HasFactory<AccountFactory> */
    use HasFactory;

    /** Codes the posting rules refer to by name rather than by primary key. */
    public const BANK = '1000';

    public const ACCOUNTS_RECEIVABLE = '1100';

    public const LAND_INVENTORY = '1200';

    public const SHARE_CAPITAL = '3000';

    public const STAND_SALES_REVENUE = '4000';

    public const COST_OF_SALES = '5000';

    public function getRouteKeyName(): string
    {
        return 'code';
    }

    /** @return HasMany<JournalLine, $this> */
    public function journalLines(): HasMany
    {
        return $this->hasMany(JournalLine::class);
    }

    #[Scope]
    protected function ofType(Builder $query, AccountType $type): Builder
    {
        return $query->where('type', $type);
    }

    #[Scope]
    protected function inReportOrder(Builder $query): Builder
    {
        return $query->orderBy('position')->orderBy('code');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => AccountType::class,
            'position' => 'integer',
        ];
    }
}
