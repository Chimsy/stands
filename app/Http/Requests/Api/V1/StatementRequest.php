<?php

namespace App\Http\Requests\Api\V1;

use App\Models\Branch;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;

/**
 * Shared input for the financial statements: which books to read, and as at
 * when.
 */
class StatementRequest extends FormRequest
{
    /** Value of the `branch` parameter that asks for every branch consolidated. */
    public const GROUP = 'group';

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            /** Omitted means the caller's own branch; "group" consolidates every branch. */
            'branch' => ['sometimes', 'string', 'max:8'],
            'from' => ['sometimes', 'date'],
            'to' => ['sometimes', 'date'],
        ];
    }

    /**
     * Null means consolidated. Anything other than the caller's own branch code
     * or "group" is rejected, so one office cannot read another's books.
     */
    public function branch(): ?Branch
    {
        $requested = $this->string('branch')->toString();

        if ($requested === '') {
            return $this->user()->branch;
        }

        if (strtolower($requested) === self::GROUP) {
            return null;
        }

        abort_unless(
            strtoupper($requested) === $this->user()->branch?->code,
            403,
            'You can only read your own branch, or the consolidated group.',
        );

        return $this->user()->branch;
    }

    public function asAt(): Carbon
    {
        return $this->date('to') ? Carbon::parse($this->date('to')) : Carbon::today();
    }

    /** Financial years here start on 1 January; a statement defaults to the year to date. */
    public function from(): Carbon
    {
        return $this->date('from') ? Carbon::parse($this->date('from')) : $this->asAt()->copy()->startOfYear();
    }
}
