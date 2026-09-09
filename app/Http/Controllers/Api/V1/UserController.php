<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use Illuminate\Http\Request;

class UserController extends Controller
{
    /** The account behind the current token, used by the front-end to restore a session. */
    public function show(Request $request): UserResource
    {
        return new UserResource($request->user());
    }
}
