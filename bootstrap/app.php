<?php

use App\Exceptions\AccountingException;
use App\Http\Middleware\EnsureUserIsAdmin;
use App\Http\Middleware\ResolveActiveBranch;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;

return Application::configure(basePath: dirname(__DIR__))
    /** This application is an API only - it serves no web pages, so no web routes are registered. */
    ->withRouting(
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'branch' => ResolveActiveBranch::class,
            'admin' => EnsureUserIsAdmin::class,
        ]);

        /**
         * Route binding is branch-scoped, so the branch has to be settled
         * before the bindings are resolved. Left unprioritised, Laravel runs
         * this after SubstituteBindings and an administrator's selection would
         * change every list on the page but none of the records behind them.
         */
        $middleware->prependToPriorityList(SubstituteBindings::class, ResolveActiveBranch::class);
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
