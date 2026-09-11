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
     * Which books to read. Null means every branch consolidated.
     *
     * An agent reads their own branch and nothing else - not another office,
     * and not the group, because a consolidated total is a head-office figure
     * that a single branch has no business seeing. An administrator may name
     * any office or ask for the group.
     */
    public function branch(): ?Branch
    {
        $requested = $this->string('branch')->toString();

        if ($requested === '') {
            return $this->user()->activeBranch();
        }

        if (strtolower($requested) === self::GROUP) {
            abort_unless(
                $this->user()->isAdmin(),
                403,
                'Consolidated statements are for administrators only.',
            );

            return null;
        }

        $branch = Branch::query()->where('code', strtoupper($requested))->first();

        abort_unless(
            $branch !== null && $this->user()->canWorkFrom($branch),
            403,
            'You can only read your own branch\'s books.',
        );

        return $branch;
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
