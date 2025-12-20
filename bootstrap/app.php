<?php

use App\Http\Middleware\AlwaysAcceptJson;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Illuminate\Database\Eloquent\ModelNotFoundException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->prependToGroup('api', AlwaysAcceptJson::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {

        // Handle 404 Not Found
        $exceptions->renderable(function (NotFoundHttpException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'message' => 'Resource not found',
                    'error' => 'Not Found'
                ], 404);
            }
        });

        // Handle Model Not Found
        $exceptions->renderable(function (ModelNotFoundException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'message' => 'Record not found',
                    'error' => 'Not Found'
                ], 404);
            }
        });

        // Handle Validation Exceptions
        $exceptions->renderable(function (ValidationException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'message' => 'Validation failed',
                    'errors' => $e->errors()
                ], 422);
            }
        });

        // Handle Authentication Exceptions
        $exceptions->renderable(function (AuthenticationException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'message' => 'Unauthenticated',
                    'error' => 'Authentication required'
                ], 401);
            }
        });

        // Handle HTTP Exceptions (403, 405, etc.)
        $exceptions->renderable(function (HttpException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'message' => $e->getMessage() ?: 'HTTP Error',
                    'error' => class_basename($e)
                ], $e->getStatusCode());
            }
        });

        // Handle ALL other exceptions for API routes
        $exceptions->renderable(function (Throwable $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                $statusCode = method_exists($e, 'getStatusCode')
                    ? $e->getStatusCode()
                    : 500;

                $response = [
                    'message' => $e->getMessage() ?: 'Server Error',
                    'error' => class_basename($e)
                ];

                // Add debug info in non-production
                if (config('app.debug')) {
                    $response['debug'] = [
                        'exception' => get_class($e),
                        'file' => $e->getFile(),
                        'line' => $e->getLine(),
                        'trace' => collect($e->getTrace())->take(5)->toArray()
                    ];
                }

                return response()->json($response, $statusCode);
            }
        });

    })
    ->withEvents([
        __DIR__ . '/../app/Listeners',
    ])
    ->create();