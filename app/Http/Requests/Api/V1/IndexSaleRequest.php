<?php

namespace App\Http\Requests\Api\V1;

use App\Enums\SaleStatus;
use App\Enums\SaleType;
use App\Http\Requests\Api\V1\Concerns\ResolvesBranchScope;
use App\Models\Branch;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Filters for the sales ledger.
 *
 * `branch` takes the same values as a statement's, so an administrator can read
 * the group dashboard and then page through the sales behind it without
 * visiting each office in turn.
 */
class IndexSaleRequest extends FormRequest
{
    use ResolvesBranchScope;

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'branch' => ['sometimes', 'string', 'max:8'],
            'status' => ['sometimes', Rule::enum(SaleStatus::class)],
            'type' => ['sometimes', Rule::enum(SaleType::class)],
            'standNumber' => ['sometimes', 'string', 'max:255'],
        ];
    }

    /** Null means every branch, which only an administrator can ask for. */
    public function branch(): ?Branch
    {
        return $this->resolveBranchScope();
    }
}
