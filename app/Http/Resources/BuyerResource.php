<?php

namespace App\Http\Resources;

use App\Models\Buyer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Buyer
 */
class BuyerResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'nationalId' => $this->national_id,
            'branch' => new BranchResource($this->whenLoaded('branch')),
        ];
    }
}
