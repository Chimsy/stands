<?php

namespace App\Http\Requests\Api\V1;

use App\Enums\PaymentMethod;
use App\Enums\SaleType;
use App\Models\Buyer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreSaleRequest extends FormRequest
{
    /**
     * Amounts are accepted in major units, the way a person types them, and
     * converted to the minor units the ledger works in.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'standNumber' => ['required', 'string'],
            'buyerId' => [
                'required',
                /** Restricted to the caller's own branch, so a sale cannot be booked against another office's buyer. */
                Rule::exists('buyers', 'id')->where('branch_id', $this->user()->branch_id),
            ],
            'type' => ['required', Rule::enum(SaleType::class)],
            'price' => ['required', 'numeric', 'min:0.01', 'max:99999999'],
            'saleDate' => ['required', 'date', 'before_or_equal:today'],
            'method' => ['required', Rule::enum(PaymentMethod::class)],
            'deposit' => ['required_if:type,'.SaleType::PaymentPlan->value, 'numeric', 'min:0', 'lt:price'],
            'instalmentCount' => ['required_if:type,'.SaleType::PaymentPlan->value, 'integer', 'min:1', 'max:120'],
        ];
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty() || $this->enum('type', SaleType::class) !== SaleType::Cash) {
                    return;
                }

                /** A cash sale settles in full on the day, so a deposit is meaningless. */
                if ($this->filled('deposit') && $this->float('deposit') > 0) {
                    $validator->errors()->add('deposit', 'A cash sale is settled in full, so it takes no deposit.');
                }
            },
        ];
    }

    public function priceCents(): int
    {
        return (int) round($this->float('price') * 100);
    }

    public function depositCents(): int
    {
        return (int) round($this->float('deposit') * 100);
    }

    public function buyer(): Buyer
    {
        return Buyer::findOrFail($this->integer('buyerId'));
    }
}
