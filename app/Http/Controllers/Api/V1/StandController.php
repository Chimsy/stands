<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\StandStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\IndexStandRequest;
use App\Http\Resources\StandResource;
use App\Models\Stand;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class StandController extends Controller
{
    /**
     * The site map draws every stand in the township at once, so this endpoint
     * deliberately returns the whole filtered set rather than a page of it.
     * Callers that only need part of the plan should narrow it with the
     * proximity or status filters.
     */
    public function index(IndexStandRequest $request): AnonymousResourceCollection
    {
        $stands = Stand::query()->orderBy('stand_number');

        if ($status = $request->enum('status', StandStatus::class)) {
            $stands->status($status);
        }

        if ($request->filled('block')) {
            $stands->where('block', $request->string('block'));
        }

        if ($request->filled('road')) {
            $stands->where('road', $request->string('road'));
        }

        if ($request->filled('search')) {
            $stands->matching($request->string('search')->toString());
        }

        if ($request->filled('radius')) {
            $stands->near($request->float('x'), $request->float('y'), $request->float('radius'));
        }

        return StandResource::collection($stands->get());
    }

    public function show(Stand $stand): StandResource
    {
        return new StandResource($stand);
    }
}
