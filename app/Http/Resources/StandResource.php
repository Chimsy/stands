<?php

namespace App\Http\Resources;

use App\Models\Stand;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Stand
 */
class StandResource extends JsonResource
{
    /**
     * The stand number is the only identifier exposed: it is unique, stable,
     * and the value the API routes on, so consumers never need the database key.
     *
     * Coordinates are metres on the site plan, origin at the plan's top-left corner.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'standNumber' => $this->stand_number,
            'status' => $this->status,
            'block' => $this->block,
            'road' => $this->road,
            'areaSqm' => $this->area_sqm,
            'price' => $this->price,
            'updatedAt' => $this->updated_at->toDateString(),
            'centroid' => ['x' => $this->centroid_x, 'y' => $this->centroid_y],
            'points' => $this->points,
        ];
    }
}
