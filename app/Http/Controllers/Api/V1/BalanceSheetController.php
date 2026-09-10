<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StatementRequest;
use App\Reports\BalanceSheet;
use Illuminate\Http\JsonResponse;

class BalanceSheetController extends Controller
{
    public function show(StatementRequest $request, BalanceSheet $balanceSheet): JsonResponse
    {
        return response()->json([
            'data' => $balanceSheet->handle($request->branch(), $request->asAt()),
        ]);
    }
}
