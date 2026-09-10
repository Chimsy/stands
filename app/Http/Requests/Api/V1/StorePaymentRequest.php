<?php

namespace App\Http\Requests\Api\V1;

use App\Enums\PaymentMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePaymentRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'min:0.01', 'max:99999999'],
            'method' => ['required', Rule::enum(PaymentMethod::class)],
            'paidOn' => ['required', 'date', 'before_or_equal:today'],
            'externalReference' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function amountCents(): int
    {
        return (int) round($this->float('amount') * 100);
    }
}
