<?php

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
        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $isRefusal = static fn (Throwable $exception): bool => $exception instanceof RuntimeException
            && in_array($exception->getMessage(), ['REFUSED: environment', 'REFUSED: driver', 'REFUSED: database-path'], true);
        $exceptions->report(function (RuntimeException $exception) use ($isRefusal) {
            return $isRefusal($exception) ? false : null;
        });
        $exceptions->render(function (RuntimeException $exception) use ($isRefusal) {
            if ($isRefusal($exception)) {
                return response($exception->getMessage(), 503)->header('Content-Type', 'text/plain');
            }
        });
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
