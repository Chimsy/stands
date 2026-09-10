<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreBuyerRequest;
use App\Http\Resources\BuyerResource;
use App\Models\Buyer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class BuyerController extends Controller
{
    private const SEARCH_LIMIT = 50;

    /**
     * Buyers registered at the caller's branch, narrowed by `search` because the
     * list exists to pick one when booking a sale.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $buyers = Buyer::query()
            ->where('branch_id', $request->user()->branch_id)
            ->when($request->filled('search'), function ($query) use ($request): void {
                $term = '%'.addcslashes($request->string('search')->toString(), '%_\\').'%';

                $query->where(fn ($match) => $match->where('name', 'like', $term)
                    ->orWhere('email', 'like', $term)
                    ->orWhere('phone', 'like', $term)
                    ->orWhere('national_id', 'like', $term));
            })
            ->orderBy('name')
            ->limit(self::SEARCH_LIMIT)
            ->get();

        return BuyerResource::collection($buyers);
    }

    public function store(StoreBuyerRequest $request): JsonResponse
    {
        $buyer = Buyer::create([
            'branch_id' => $request->user()->branch_id,
            'name' => $request->string('name'),
            'email' => $request->input('email'),
            'phone' => $request->input('phone'),
            'national_id' => $request->input('nationalId'),
        ]);

        return (new BuyerResource($buyer))->response()->setStatusCode(Response::HTTP_CREATED);
    }
}
