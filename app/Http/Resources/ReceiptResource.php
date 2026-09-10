<?php

namespace App\Http\Resources;

use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Everything printed on a receipt.
 *
 * The figures are read at request time rather than stored on the payment, so a
 * reprinted receipt shows the same amount received but an up-to-date balance.
 *
 * @mixin Payment
 */
class ReceiptResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $sale = $this->sale;

        return [
            'receiptNumber' => $this->receipt_number,
            'paidOn' => $this->paid_on->toDateString(),
            'issuedAt' => $this->created_at?->toIso8601String(),
            'amount' => $this->amount_cents / 100,
            'method' => $this->method,
            'externalReference' => $this->external_reference,
            'receivedBy' => $this->receivedBy?->name,
            'branch' => new BranchResource($this->branch),
            'buyer' => new BuyerResource($sale->buyer),
            'sale' => [
                'reference' => $sale->reference,
                'saleDate' => $sale->sale_date->toDateString(),
                'type' => $sale->type,
                'price' => $sale->price_cents / 100,
                'paid' => $sale->paid_cents / 100,
                'outstanding' => $sale->outstanding_cents / 100,
            ],
            'stand' => [
                'standNumber' => $sale->stand->stand_number,
                'block' => $sale->stand->block,
                'road' => $sale->stand->road,
                'areaSqm' => $sale->stand->area_sqm,
                'township' => $sale->stand->sitePlan->name,
            ],
        ];
    }
}
