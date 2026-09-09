<?php

namespace App\Http\Resources;

use App\Models\SitePlan;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin SitePlan
 */
class SitePlanResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'name' => $this->name,
            'subtitle' => $this->subtitle,
            'authority' => $this->authority,
            'note' => $this->note,
            'northRotation' => $this->north_rotation,
            'bounds' => $this->bounds,
            'boundary' => $this->boundary,
            'roads' => $this->roads,
            'zones' => $this->zones,
            'blocks' => $this->blocks,
        ];
    }
}
