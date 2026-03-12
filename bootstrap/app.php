<?php

use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);

        $middleware->web(append: [
            HandleAppearance::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);

        $middleware->alias([
            'is_admin' => \App\Http\Middleware\IsAdmin::class,
            'is_docente' => \App\Http\Middleware\IsDocente::class,
            'is_estudiante' => \App\Http\Middleware\IsStudent::class,
            'is_ayudante' => \App\Http\Middleware\IsAyudante::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Handle LoginException - convert to validation errors with specific error codes
        $exceptions->render(function (\App\Exceptions\LoginException $e, $request) {
            // Log the exception
            \Illuminate\Support\Facades\Log::channel('single')->warning('[LOGIN EXCEPTION]', [
                'error_code' => $e->errorCode->value,
                'message'    => $e->getMessage(),
                'ip'         => $request->ip(),
            ]);

            // If it's an API request or expecting JSON, return JSON response
            if ($request->expectsJson()) {
                return response()->json([
                    'message'    => $e->getMessage(),
                    'error_code' => $e->errorCode->value,
                    'retry_after' => $e->retryAfter,
                ], 422);
            }

            // For POST requests (form submissions), redirect back with Fortify-compatible errors
            if ($request->method() === 'POST') {
                // Store error details in session for retrieval by frontend
                $request->session()->put('login_error', [
                    'code' => $e->errorCode->value,
                    'message' => $e->getMessage(),
                    'retry_after' => $e->retryAfter,
                ]);

                return back()
                    ->withInput($request->input())
                    ->withErrors([
                        'email' => $e->getMessage(),
                    ]);
            }

            // For other requests, don't catch - let default error handling occur
            return null;
        });
    })->create();
