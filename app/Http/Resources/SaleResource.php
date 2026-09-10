<?php

namespace App\Http\Resources;

use App\Models\Sale;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Sale
 */
class SaleResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'reference' => $this->reference,
            'saleDate' => $this->sale_date->toDateString(),
            'type' => $this->type,
            'status' => $this->status,
            'price' => $this->price_cents / 100,
            'cost' => $this->cost_cents / 100,
            'deposit' => $this->deposit_cents / 100,
            'paid' => $this->paid_cents / 100,
            'outstanding' => $this->outstanding_cents / 100,
            'instalmentCount' => $this->instalment_count,
            'standNumber' => $this->whenLoaded('stand', fn (): string => $this->stand->stand_number),
            'buyer' => new BuyerResource($this->whenLoaded('buyer')),
            'soldBy' => $this->whenLoaded('soldBy', fn (): ?string => $this->soldBy?->name),
            'branch' => new BranchResource($this->whenLoaded('branch')),
            'instalments' => InstalmentResource::collection($this->whenLoaded('instalments')),
            'payments' => PaymentResource::collection($this->whenLoaded('payments')),
        ];
    }
}
