<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;

it('issues a token for correct credentials', function () {
    $user = User::factory()->create([
        'email' => 'agent@example.com',
        'password' => Hash::make('correct-horse'),
    ]);

    $response = $this->postJson(route('api.v1.login'), [
        'email' => 'agent@example.com',
        'password' => 'correct-horse',
        'deviceName' => 'Sales laptop',
    ]);

    $response->assertCreated()
        ->assertJsonPath('user.id', $user->id)
        ->assertJsonPath('user.email', 'agent@example.com')
        ->assertJsonStructure(['token', 'user' => ['id', 'name', 'email']]);

    expect($user->tokens()->pluck('name')->all())->toBe(['Sales laptop']);
});

it('returns a token that authenticates subsequent requests', function () {
    User::factory()->create([
        'email' => 'agent@example.com',
        'password' => Hash::make('correct-horse'),
    ]);

    $token = $this->postJson(route('api.v1.login'), [
        'email' => 'agent@example.com',
        'password' => 'correct-horse',
    ])->json('token');

    $this->withToken($token)
        ->getJson(route('api.v1.user.show'))
        ->assertOk()
        ->assertJsonPath('data.email', 'agent@example.com');
});

it('names the token after the user agent when no device name is given', function () {
    User::factory()->create([
        'email' => 'agent@example.com',
        'password' => Hash::make('correct-horse'),
    ]);

    $this->withHeader('User-Agent', 'RiverstoneBrowser/1.0')
        ->postJson(route('api.v1.login'), [
            'email' => 'agent@example.com',
            'password' => 'correct-horse',
        ])
        ->assertCreated();

    expect(User::first()->tokens()->value('name'))->toBe('RiverstoneBrowser/1.0');
});

it('returns 422 for a wrong password', function () {
    User::factory()->create([
        'email' => 'agent@example.com',
        'password' => Hash::make('correct-horse'),
    ]);

    $this->postJson(route('api.v1.login'), [
        'email' => 'agent@example.com',
        'password' => 'wrong',
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['email' => 'These credentials do not match our records.']);
});

it('returns the same error for an unknown email as for a wrong password', function () {
    $this->postJson(route('api.v1.login'), [
        'email' => 'nobody@example.com',
        'password' => 'anything',
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['email' => 'These credentials do not match our records.']);
});

it('returns 422 when credentials are missing', function () {
    $this->postJson(route('api.v1.login'), [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['email', 'password']);
});

it('returns 429 after five failed attempts for the same account', function () {
    User::factory()->create([
        'email' => 'agent@example.com',
        'password' => Hash::make('correct-horse'),
    ]);

    foreach (range(1, 5) as $attempt) {
        $this->postJson(route('api.v1.login'), [
            'email' => 'agent@example.com',
            'password' => 'wrong',
        ])->assertUnprocessable();
    }

    $this->postJson(route('api.v1.login'), [
        'email' => 'agent@example.com',
        'password' => 'correct-horse',
    ])->assertTooManyRequests();
});

it('revokes only the token that made the logout request', function () {
    $user = User::factory()->create();
    $keptToken = $user->createToken('Other device');
    $currentToken = $user->createToken('This device');

    $this->withToken($currentToken->plainTextToken)
        ->postJson(route('api.v1.logout'))
        ->assertNoContent();

    expect($user->tokens()->pluck('id')->all())->toBe([$keptToken->accessToken->id]);
});

it('returns 401 when logging out without a token', function () {
    $this->postJson(route('api.v1.logout'))->assertUnauthorized();
});

it('returns 401 for a token that has been revoked', function () {
    $user = User::factory()->create();
    $token = $user->createToken('This device');
    $user->tokens()->delete();

    $this->withToken($token->plainTextToken)
        ->getJson(route('api.v1.user.show'))
        ->assertUnauthorized();
});
