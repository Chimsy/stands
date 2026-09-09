<?php

/**
 * This application is an API only. These cover the contract that comes with
 * that: no web pages are served, and the health endpoint the platform polls
 * stays reachable without a token.
 */
it('exposes a health check without authentication', function () {
    $this->get('/up')->assertOk();
});

it('serves no web pages', function () {
    $this->get('/')->assertNotFound();
});

it('answers unauthenticated API requests with 401 rather than a redirect', function () {
    $this->getJson(route('api.v1.stands.index'))
        ->assertUnauthorized()
        ->assertHeader('content-type', 'application/json');
});
