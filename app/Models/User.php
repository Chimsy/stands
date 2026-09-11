<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\UserRole;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['branch_id', 'name', 'email', 'role', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The branch this request is being worked from, set by
     * `App\Http\Middleware\ResolveActiveBranch` once per request.
     *
     * Not persisted: an administrator's choice of office lasts for the request,
     * and their home branch is what they fall back to.
     */
    private ?Branch $workingBranch = null;

    /**
     * The office this user works from. Nullable in the schema only because the
     * column was added to an existing table; the application requires it, and a
     * user without a branch can see nothing and sell nothing.
     *
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function isAdmin(): bool
    {
        return $this->role === UserRole::Admin;
    }

    /**
     * The branch every scoped query in this request answers for.
     *
     * An agent's is always their own. An administrator's is whichever office
     * they asked for, defaulting to their home branch so an unqualified request
     * still lands somewhere definite rather than reading across the group.
     */
    public function activeBranch(): ?Branch
    {
        return $this->workingBranch ?? $this->branch;
    }

    /** @see ResolveActiveBranch — the only caller; nothing else may widen a user's reach. */
    public function workFrom(Branch $branch): void
    {
        $this->workingBranch = $branch;
    }

    /** An agent is confined to their own office; an administrator may use any. */
    public function canWorkFrom(Branch $branch): bool
    {
        return $this->isAdmin() || $this->branch_id === $branch->id;
    }

    /**
     * The offices this user may switch between, which is what the front-end
     * renders its branch picker from.
     *
     * @return Collection<int, Branch>
     */
    public function accessibleBranches(): Collection
    {
        if ($this->isAdmin()) {
            return Branch::query()->orderBy('name')->get();
        }

        return Branch::query()->whereKey($this->branch_id)->get();
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
        ];
    }
}
