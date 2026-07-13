<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->api(prepend: [\App\Http\Middleware\RequestId::class]);
        $middleware->alias([
            'api.auth' => \App\Http\Middleware\AuthenticateApiToken::class,
            'role' => \App\Http\Middleware\RequireRole::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );
        $exceptions->render(function (\Throwable $e, Request $request) {
            if (!$request->is('api/*')) return null;
            $status = match (true) {
                $e instanceof ValidationException => 422,
                $e instanceof AuthorizationException => 403,
                $e instanceof ModelNotFoundException => 404,
                $e instanceof HttpExceptionInterface => $e->getStatusCode(),
                default => 500,
            };
            $code = match ($status) {
                401 => 'UNAUTHENTICATED', 403 => 'FORBIDDEN', 404 => 'NOT_FOUND',
                422 => 'VALIDATION_ERROR', 429 => 'RATE_LIMITED', default => 'SERVER_ERROR',
            };
            $message = $status === 500 && !config('app.debug') ? 'Terjadi kesalahan pada server.' : $e->getMessage();
            $error = ['code' => $code, 'message' => $message];
            if ($e instanceof ValidationException) $error['fields'] = $e->errors();
            return response()->json(['error' => $error, 'request_id' => $request->attributes->get('request_id')], $status);
        });
    })->create();
