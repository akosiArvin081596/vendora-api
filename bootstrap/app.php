<?php

use App\Exceptions\InsufficientCostLayersException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'store.context' => \App\Http\Middleware\SetStoreContext::class,
            'idempotent' => \App\Http\Middleware\EnsureIdempotency::class,
        ]);

        $middleware->appendToGroup('api', [
            \App\Http\Middleware\EnsureIdempotency::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->renderable(function (\Illuminate\Validation\ValidationException $e, Request $request) {
            if ($request->is('api/products') && $request->isMethod('POST')) {
                \Log::warning('Product creation validation failed', [
                    'errors' => $e->errors(),
                    'input_keys' => array_keys($request->all()),
                ]);
            }

            return null; // Let Laravel handle it normally
        });

        $exceptions->renderable(function (InsufficientCostLayersException $e, Request $request) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Quantity exceeds available cost layers.',
                    'errors' => [
                        'quantity' => ['Quantity exceeds available cost layers.'],
                    ],
                ], 422);
            }
        });
    })->create();
