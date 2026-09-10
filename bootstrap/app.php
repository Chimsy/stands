<?php

use App\Exceptions\AccountingException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    /** This application is an API only - it serves no web pages, so no web routes are registered. */
    ->withRouting(
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        /**
         * A refusal to put the books into an impossible state is the caller's
         * problem to fix, so it reads like a validation failure rather than a
         * server fault.
         */
        $exceptions->render(fn (AccountingException $e) => response()->json([
            'message' => $e->getMessage(),
        ], 422));
    })->create();
