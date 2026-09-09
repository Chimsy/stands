<?php

namespace App\Http\Requests\Api\V1;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => ['required', 'string'],

            /** Labels the issued token so a user can tell their sessions apart when revoking. */
            'deviceName' => ['sometimes', 'string', 'max:255'],
        ];
    }

    /**
     * Resolves the user for the supplied credentials.
     *
     * @throws ValidationException
     */
    public function authenticate(): User
    {
        $user = User::query()->where('email', $this->string('email')->toString())->first();

        /**
         * Hash::check runs even when no user matched so that a missing account
         * and a wrong password take the same amount of time to answer.
         */
        if (! Hash::check($this->string('password')->toString(), $user?->password ?? '')) {
            throw ValidationException::withMessages([
                'email' => __('auth.failed'),
            ]);
        }

        return $user;
    }

    public function deviceName(): string
    {
        return $this->string('deviceName')->whenEmpty(
            fn () => str($this->userAgent() ?? 'Unknown device')->limit(255),
        )->toString();
    }
}
