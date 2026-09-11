<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\SellStand;
use App\Enums\PaymentMethod;
use App\Enums\SaleStatus;
use App\Enums\SaleType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\IndexSaleRequest;
use App\Http\Requests\Api\V1\StoreSaleRequest;
use App\Http\Resources\SaleResource;
use App\Models\Sale;
use App\Models\Stand;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class SaleController extends Controller
{
    private const PER_PAGE = 25;

    /**
     * Sales booked at the branch the request is worked from, most recent first,
     * or across every branch when an administrator asks for the group.
     *
     * Unlike the stand list, which the map needs whole, this is paginated: it
     * is a ledger of activity that only grows.
     */
    public function index(IndexSaleRequest $request): AnonymousResourceCollection
    {
        $sales = Sale::query()
            ->forBranch($request->branch())
            ->with(['stand:id,stand_number', 'buyer', 'branch'])
            ->withSum('payments', 'amount_cents')
            ->when(
                $request->enum('status', SaleStatus::class),
                fn ($query, SaleStatus $status) => $query->where('status', $status),
            )
            ->when(
                $request->enum('type', SaleType::class),
                fn ($query, SaleType $type) => $query->where('type', $type),
            )
            /** Lets a stand's page find the sale behind it without scanning the ledger. */
            ->when(
                $request->filled('standNumber'),
                fn ($query) => $query->whereHas(
                    'stand',
                    fn ($stand) => $stand->where('stand_number', $request->string('standNumber')),
                ),
            )
            ->latest('sale_date')
            ->latest('id')
            ->paginate(self::PER_PAGE);

        return SaleResource::collection($sales);
    }

    public function show(Sale $sale): SaleResource
    {
        $sale->load(['stand:id,stand_number,block,road', 'buyer', 'branch', 'soldBy', 'instalments', 'payments'])
            ->loadSum('payments', 'amount_cents');

        return new SaleResource($sale);
    }

    public function store(StoreSaleRequest $request, SellStand $sellStand): JsonResponse
    {
        /** Resolved through the branch-scoped binding, so another branch's stand reads as missing. */
        $stand = (new Stand)->resolveRouteBinding($request->string('standNumber'));

        abort_if($stand === null, Response::HTTP_NOT_FOUND, 'That stand is not on your branch\'s plan.');

        $sale = $sellStand->handle(
            stand: $stand,
            buyer: $request->buyer(),
            type: $request->enum('type', SaleType::class),
            priceCents: $request->priceCents(),
            saleDate: $request->date('saleDate'),
            method: $request->enum('method', PaymentMethod::class),
            soldBy: $request->user(),
            depositCents: $request->depositCents(),
            instalmentCount: $request->integer('instalmentCount'),
        );

        $sale->load(['stand:id,stand_number', 'buyer', 'branch', 'instalments', 'payments'])
            ->loadSum('payments', 'amount_cents');

        return (new SaleResource($sale))->response()->setStatusCode(Response::HTTP_CREATED);
    }
}
