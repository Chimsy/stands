<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StatementRequest;
use App\Reports\IncomeStatement;
use Illuminate\Http\JsonResponse;

class IncomeStatementController extends Controller
{
    public function show(StatementRequest $request, IncomeStatement $incomeStatement): JsonResponse
    {
        return response()->json([
            'data' => $incomeStatement->handle($request->branch(), $request->from(), $request->asAt()),
        ]);
    }
}
