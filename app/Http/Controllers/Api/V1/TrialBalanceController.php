<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StatementRequest;
use App\Reports\TrialBalance;
use Illuminate\Http\JsonResponse;

class TrialBalanceController extends Controller
{
    public function show(StatementRequest $request, TrialBalance $trialBalance): JsonResponse
    {
        return response()->json([
            'data' => $trialBalance->handle($request->branch(), $request->asAt()),
        ]);
    }
}
