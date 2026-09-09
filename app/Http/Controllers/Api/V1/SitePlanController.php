<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\SitePlanResource;
use App\Models\SitePlan;

class SitePlanController extends Controller
{
    /**
     * The application serves one township, so the plan is a singleton resource.
     * If more estates are added this should resolve the caller's current plan
     * rather than the only row in the table.
     */
    public function show(): SitePlanResource
    {
        return new SitePlanResource(SitePlan::query()->firstOrFail());
    }
}
