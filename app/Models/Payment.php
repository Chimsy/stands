<?php

namespace App\Models;

use App\Enums\PaymentMethod;
use App\Models\Concerns\ScopedToUserBranch;
use Database\Factories\PaymentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Money received against a sale. Each payment is also the receipt issued for
 * it, which is why the receipt number lives here and is never reused.
 */
#[Fillable([
    'branch_id',
    'sale_id',
    'received_by',
    'receipt_number',
    'paid_on',
    'amount_cents',
    'method',
    'external_reference',
])]
class Payment extends Model
{
    /** @use HasFactory<PaymentFactory> */
    use HasFactory, ScopedToUserBranch;

    public function getRouteKeyName(): string
    {
        return 'receipt_number';
    }

    /** @return BelongsTo<Branch, $this> */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /** @return BelongsTo<Sale, $this> */
    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    /** @return BelongsTo<User, $this> */
    public function receivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    /** @return MorphMany<JournalEntry, $this> */
    public function journalEntries(): MorphMany
    {
        return $this->morphMany(JournalEntry::class, 'source');
    }

    #[Scope]
    protected function forBranch(Builder $query, ?Branch $branch): Builder
    {
        return $query->when($branch, fn (Builder $query) => $query->whereBelongsTo($branch));
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'paid_on' => 'date',
            'amount_cents' => 'integer',
            'method' => PaymentMethod::class,
        ];
    }
}
