<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\SitePlanResource;
use App\Models\SitePlan;
use Illuminate\Http\Request;

class SitePlanController extends Controller
{
    /**
     * The plan for the caller's own branch. Each branch runs one township, so
     * this stays a singleton from the front-end's point of view; a branch with
     * several estates would need the plan naming which one it wants.
     */
    public function show(Request $request): SitePlanResource
    {
        return new SitePlanResource(
            SitePlan::query()->where('branch_id', $request->user()->activeBranch()?->id)->firstOrFail(),
        );
    }
}
