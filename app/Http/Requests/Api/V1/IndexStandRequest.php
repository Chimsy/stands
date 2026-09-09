<?php

namespace App\Http\Requests\Api\V1;

use App\Enums\StandStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexStandRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'status' => ['sometimes', Rule::enum(StandStatus::class)],
            'block' => ['sometimes', 'string', 'max:255'],
            'road' => ['sometimes', 'string', 'max:255'],
            'search' => ['sometimes', 'string', 'max:255'],

            /** Proximity search in plan-space metres; supplying one means supplying all three. */
            'x' => ['required_with:y,radius', 'numeric'],
            'y' => ['required_with:x,radius', 'numeric'],
            'radius' => ['required_with:x,y', 'numeric', 'min:0', 'max:10000'],
        ];
    }
}
