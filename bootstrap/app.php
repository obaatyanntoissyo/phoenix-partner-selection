<?php

use App\Exceptions\ApplicantFlowException;
use App\Http\Middleware\ApplicantSession;
use App\Http\Middleware\SecurityHeaders;
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
        $middleware->append(SecurityHeaders::class);
        $middleware->alias(['applicant' => ApplicantSession::class]);
        $middleware->redirectGuestsTo(fn () => route('admin.login'));
        $middleware->trustProxies(at: env('TRUSTED_PROXIES') ? explode(',', env('TRUSTED_PROXIES')) : null);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
        $exceptions->dontReport([ApplicantFlowException::class]);
        $exceptions->render(function (ApplicantFlowException $e, Request $request) {
            $info = $e->info();
            if ($request->expectsJson()) {
                return response()->json(['error' => $e->kind], $info['status']);
            }

            return response()->view('applicant.error', ['info' => $info, 'kind' => $e->kind], $info['status']);
        });
    })->create();
