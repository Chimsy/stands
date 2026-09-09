<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\LoginRequest;
use App\Http\Resources\UserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Issues and revokes the API tokens the front-end authenticates with.
 */
class TokenController extends Controller
{
    public function store(LoginRequest $request): JsonResponse
    {
        $user = $request->authenticate();

        return response()->json([
            'token' => $user->createToken($request->deviceName())->plainTextToken,
            'user' => new UserResource($user),
        ], Response::HTTP_CREATED);
    }

    /**
     * Revokes only the token that made this request, leaving the user signed in
     * on any other device.
     */
    public function destroy(Request $request): Response
    {
        $request->user()->currentAccessToken()->delete();

        return response()->noContent();
    }
}
