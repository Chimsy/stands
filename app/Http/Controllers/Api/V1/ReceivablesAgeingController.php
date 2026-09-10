<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StatementRequest;
use App\Reports\ReceivablesAgeing;
use Illuminate\Http\JsonResponse;

class ReceivablesAgeingController extends Controller
{
    public function show(StatementRequest $request, ReceivablesAgeing $receivablesAgeing): JsonResponse
    {
        return response()->json([
            'data' => $receivablesAgeing->handle($request->branch(), $request->asAt()),
        ]);
    }
}
