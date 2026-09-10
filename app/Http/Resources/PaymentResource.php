<?php

namespace App\Http\Resources;

use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A payment as it appears in a list. The printable receipt itself is
 * ReceiptResource, which carries the buyer and sale detail too.
 *
 * @mixin Payment
 */
class PaymentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'receiptNumber' => $this->receipt_number,
            'paidOn' => $this->paid_on->toDateString(),
            'amount' => $this->amount_cents / 100,
            'method' => $this->method,
            'externalReference' => $this->external_reference,
            'saleReference' => $this->whenLoaded('sale', fn (): string => $this->sale->reference),
            'standNumber' => $this->whenLoaded('sale', fn (): ?string => $this->sale->stand?->stand_number),
            'buyerName' => $this->whenLoaded('sale', fn (): ?string => $this->sale->buyer?->name),
            'branch' => new BranchResource($this->whenLoaded('branch')),
        ];
    }
}
