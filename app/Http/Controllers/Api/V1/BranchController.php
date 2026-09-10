<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\BranchResource;
use App\Models\Branch;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class BranchController extends Controller
{
    /** Every branch, so the statements screen can offer a consolidated view. */
    public function index(): AnonymousResourceCollection
    {
        return BranchResource::collection(Branch::query()->orderBy('name')->get());
    }
}
