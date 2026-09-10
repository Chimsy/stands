<?php

namespace App\Http\Resources;

use App\Models\Instalment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Instalment
 */
class InstalmentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'sequence' => $this->sequence,
            'dueDate' => $this->due_date->toDateString(),
            'amount' => $this->amount_cents / 100,
            'paid' => $this->paid_cents / 100,
            'outstanding' => $this->outstanding_cents / 100,
            'isSettled' => $this->is_settled,
            'isOverdue' => $this->is_overdue,
        ];
    }
}
