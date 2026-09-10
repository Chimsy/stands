<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One scheduled repayment on a payment-plan sale.
 */
#[Fillable(['sale_id', 'sequence', 'due_date', 'amount_cents', 'paid_cents'])]
class Instalment extends Model
{
    /** @return BelongsTo<Sale, $this> */
    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    /** @return Attribute<int, never> */
    protected function outstandingCents(): Attribute
    {
        return Attribute::get(fn (): int => max(0, $this->amount_cents - $this->paid_cents));
    }

    /** @return Attribute<bool, never> */
    protected function isSettled(): Attribute
    {
        return Attribute::get(fn (): bool => $this->paid_cents >= $this->amount_cents);
    }

    /** Due, and still not covered in full. */
    protected function isOverdue(): Attribute
    {
        return Attribute::get(fn (): bool => ! $this->is_settled && $this->due_date->isPast());
    }

    #[Scope]
    protected function unsettled(Builder $query): Builder
    {
        return $query->whereColumn('paid_cents', '<', 'amount_cents');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'due_date' => 'date',
            'sequence' => 'integer',
            'amount_cents' => 'integer',
            'paid_cents' => 'integer',
        ];
    }
}
