<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin User
 */
class UserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'role' => $this->role->value,
            'isAdmin' => $this->isAdmin(),
            /** The office this request answered for; every list and report is scoped to it. */
            'branch' => $this->activeBranch() ? new BranchResource($this->activeBranch()) : null,
            /** The offices this user may switch between - one for an agent, all of them for an administrator. */
            'branches' => BranchResource::collection($this->accessibleBranches()),
        ];
    }
}
