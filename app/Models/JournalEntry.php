<?php

namespace App\Models;

use Database\Factories\JournalEntryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * One balanced double-entry transaction.
 *
 * Entries are append-only. Nothing in the application amends or deletes a
 * posted entry; a mistake is corrected by posting a reversing entry, which
 * keeps the audit trail intact.
 */
#[Fillable([
    'branch_id',
    'reference',
    'entry_date',
    'description',
    'source_type',
    'source_id',
    'recorded_by',
])]
class JournalEntry extends Model
{
    /** @use HasFactory<JournalEntryFactory> */
    use HasFactory;

    public function getRouteKeyName(): string
    {
        return 'reference';
    }

    /** @return BelongsTo<Branch, $this> */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /** @return HasMany<JournalLine, $this> */
    public function lines(): HasMany
    {
        return $this->hasMany(JournalLine::class);
    }

    /** The sale or payment the entry was raised for. */
    public function source(): MorphTo
    {
        return $this->morphTo();
    }

    /** @return BelongsTo<User, $this> */
    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    #[Scope]
    protected function forBranch(Builder $query, ?Branch $branch): Builder
    {
        return $query->when($branch, fn (Builder $query) => $query->whereBelongsTo($branch));
    }

    /** Entries dated on or before `$date`, which is how every statement is cut. */
    #[Scope]
    protected function upTo(Builder $query, string $date): Builder
    {
        return $query->whereDate('entry_date', '<=', $date);
    }

    #[Scope]
    protected function between(Builder $query, string $from, string $to): Builder
    {
        return $query->whereBetween('entry_date', [$from, $to]);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'entry_date' => 'date',
        ];
    }
}
