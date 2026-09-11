<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\IndexReceiptRequest;
use App\Http\Resources\PaymentResource;
use App\Http\Resources\ReceiptResource;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Receipts issued at the caller's branch. A receipt is a payment seen from the
 * buyer's side, which is why both live on the same record.
 */
class ReceiptController extends Controller
{
    private const PER_PAGE = 25;

    /**
     * Receipts issued at the branch the request is worked from, or across every
     * branch when an administrator asks for the group.
     */
    public function index(IndexReceiptRequest $request): AnonymousResourceCollection
    {
        $payments = Payment::query()
            ->forBranch($request->branch())
            ->with(['sale.stand:id,stand_number', 'sale.buyer', 'branch'])
            ->when($request->filled('search'), function ($query) use ($request): void {
                $term = '%'.addcslashes($request->string('search')->toString(), '%_\\').'%';

                $query->where(fn ($match) => $match->where('receipt_number', 'like', $term)
                    ->orWhereHas('sale', fn ($sale) => $sale->where('reference', 'like', $term)
                        ->orWhereHas('buyer', fn ($buyer) => $buyer->where('name', 'like', $term))
                        ->orWhereHas('stand', fn ($stand) => $stand->where('stand_number', 'like', $term))));
            })
            ->latest('paid_on')
            ->latest('id')
            ->paginate(self::PER_PAGE);

        return PaymentResource::collection($payments);
    }

    /** The printable receipt. The front-end renders and prints it; there is no PDF on this side. */
    public function show(Payment $payment): ReceiptResource
    {
        $payment->load(['branch', 'receivedBy', 'sale.buyer', 'sale.stand.sitePlan']);

        return new ReceiptResource($payment);
    }
}
