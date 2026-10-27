<?php

// Re-enrutar autoloader si vendor fue compartido por junction en un worktree
$localApp = realpath(__DIR__ . '/../app');
foreach (spl_autoload_functions() as $autoloader) {
    if (is_array($autoloader) && $autoloader[0] instanceof \Composer\Autoload\ClassLoader) {
        $composerLoader = $autoloader[0];
        $overrides = [];
        foreach ($composerLoader->getClassMap() as $class => $file) {
            if (str_starts_with($class, 'App\\')) {
                $rel = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, substr($class, 4)) . '.php';
                $candidate = $localApp . DIRECTORY_SEPARATOR . $rel;
                if (file_exists($candidate)) {
                    $overrides[$class] = $candidate;
                }
            }
        }
        if (!empty($overrides)) {
            $composerLoader->addClassMap($overrides);
        }
        break;
    }
}

use App\Http\Middleware\ForcePasswordChange;
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

        // Recorta también las contraseñas (ver App\Http\Middleware\TrimStrings).
        $middleware->replace(
            \Illuminate\Foundation\Http\Middleware\TrimStrings::class,
            \App\Http\Middleware\TrimStrings::class,
        );

        $middleware->web(append: [
            HandleAppearance::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
            ForcePasswordChange::class,
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
        $responderLogin = function (\App\Exceptions\LoginException $e, $request) {
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

                // La contraseña no se guarda en la sesión con el resto del input.
                return back()
                    ->withInput($request->except('password'))
                    ->withErrors([
                        'email' => $e->getMessage(),
                    ]);
            }

            // For other requests, don't catch - let default error handling occur
            return null;
        };

        $exceptions->render($responderLogin);

        // El límite de intentos del login (throttle de Fortify) llega como un 429
        // que Inertia mostraría en un modal; se responde como un error del login
        // para que el formulario muestre la pausa con su cuenta regresiva.
        $exceptions->render(function (\Illuminate\Http\Exceptions\ThrottleRequestsException $e, $request) use ($responderLogin) {
            if (! $request->routeIs('login.store') || $request->expectsJson()) {
                return null;
            }

            return $responderLogin(new \App\Exceptions\LoginException(
                \App\Enums\LoginErrorCode::RATE_LIMIT_EXCEEDED,
                (int) ($e->getHeaders()['Retry-After'] ?? 60),
            ), $request);
        });
    })->create();
