<?php

namespace App\Http\Requests\Api\V1;

use App\Http\Requests\Api\V1\Concerns\ResolvesBranchScope;
use App\Models\Branch;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Filters for the receipts ledger. `branch` behaves exactly as it does on the
 * sales ledger and the statements.
 */
class IndexReceiptRequest extends FormRequest
{
    use ResolvesBranchScope;

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'branch' => ['sometimes', 'string', 'max:8'],
            'search' => ['sometimes', 'string', 'max:255'],
        ];
    }

    /** Null means every branch, which only an administrator can ask for. */
    public function branch(): ?Branch
    {
        return $this->resolveBranchScope();
    }
}
