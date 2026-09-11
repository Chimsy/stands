<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StatementRequest;
use App\Reports\SalesDashboard;
use Illuminate\Http\JsonResponse;

/**
 * The head-office view of how the stands are selling.
 *
 * It takes the same scope as a statement - a branch code, or "group" for every
 * branch consolidated - so the dashboard and the books can be read side by side
 * for the same office over the same dates.
 */
class DashboardController extends Controller
{
    public function show(StatementRequest $request, SalesDashboard $dashboard): JsonResponse
    {
        return response()->json([
            'data' => $dashboard->handle($request->branch(), $request->from(), $request->asAt()),
        ]);
    }
}
