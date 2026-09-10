<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
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

    public function index(Request $request): AnonymousResourceCollection
    {
        $payments = Payment::query()
            ->forBranch($request->user()->branch)
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
