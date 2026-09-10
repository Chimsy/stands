<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\RecordPayment;
use App\Enums\PaymentMethod;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StorePaymentRequest;
use App\Http\Resources\ReceiptResource;
use App\Models\Sale;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

/**
 * Receipting money against a sale. Modelled as creating a payment on the sale
 * rather than as an action on it, because the receipt is the thing being made.
 */
class SalePaymentController extends Controller
{
    public function store(StorePaymentRequest $request, Sale $sale, RecordPayment $recordPayment): JsonResponse
    {
        $payment = $recordPayment->handle(
            sale: $sale,
            amountCents: $request->amountCents(),
            method: $request->enum('method', PaymentMethod::class),
            paidOn: $request->date('paidOn'),
            receivedBy: $request->user(),
            externalReference: $request->input('externalReference'),
        );

        $payment->load(['branch', 'receivedBy', 'sale.buyer', 'sale.stand.sitePlan']);

        return (new ReceiptResource($payment))->response()->setStatusCode(Response::HTTP_CREATED);
    }
}
