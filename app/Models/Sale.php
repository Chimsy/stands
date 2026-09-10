<?php

namespace App\Models;

use App\Enums\SaleStatus;
use App\Enums\SaleType;
use App\Models\Concerns\ScopedToUserBranch;
use Database\Factories\SaleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * An agreement of sale over one stand.
 *
 * Revenue is recognised in full on `sale_date`: control of the stand passes to
 * the buyer at signing, so the whole price is taken to revenue and the unpaid
 * portion is carried as a receivable rather than deferred.
 */
#[Fillable([
    'branch_id',
    'stand_id',
    'buyer_id',
    'sold_by',
    'reference',
    'sale_date',
    'type',
    'status',
    'price_cents',
    'cost_cents',
    'deposit_cents',
    'instalment_count',
])]
class Sale extends Model
{
    /** @use HasFactory<SaleFactory> */
    use HasFactory, ScopedToUserBranch;

    public function getRouteKeyName(): string
    {
        return 'reference';
    }

    /** @return BelongsTo<Branch, $this> */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /** @return BelongsTo<Stand, $this> */
    public function stand(): BelongsTo
    {
        return $this->belongsTo(Stand::class);
    }

    /** @return BelongsTo<Buyer, $this> */
    public function buyer(): BelongsTo
    {
        return $this->belongsTo(Buyer::class);
    }

    /** @return BelongsTo<User, $this> */
    public function soldBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sold_by');
    }

    /** @return HasMany<Instalment, $this> */
    public function instalments(): HasMany
    {
        return $this->hasMany(Instalment::class)->orderBy('sequence');
    }

    /** @return HasMany<Payment, $this> */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /** @return MorphMany<JournalEntry, $this> */
    public function journalEntries(): MorphMany
    {
        return $this->morphMany(JournalEntry::class, 'source');
    }

    /**
     * Total received against this sale.
     *
     * Prefers the aggregate loaded by `withSum('payments', 'amount_cents')` so
     * listing many sales does not run a query per row.
     *
     * @return Attribute<int, never>
     */
    protected function paidCents(): Attribute
    {
        return Attribute::get(fn (): int => (int) ($this->attributes['payments_sum_amount_cents']
            ?? $this->payments()->sum('amount_cents')));
    }

    /** @return Attribute<int, never> */
    protected function outstandingCents(): Attribute
    {
        return Attribute::get(fn (): int => max(0, $this->price_cents - $this->paid_cents));
    }

    #[Scope]
    protected function forBranch(Builder $query, ?Branch $branch): Builder
    {
        return $query->when($branch, fn (Builder $query) => $query->whereBelongsTo($branch));
    }

    #[Scope]
    protected function outstanding(Builder $query): Builder
    {
        return $query->where('status', SaleStatus::Outstanding);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sale_date' => 'date',
            'type' => SaleType::class,
            'status' => SaleStatus::class,
            'price_cents' => 'integer',
            'cost_cents' => 'integer',
            'deposit_cents' => 'integer',
            'instalment_count' => 'integer',
        ];
    }
}
