<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Session\TokenMismatchException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
         $middleware->trustProxies(at: '*');
         $middleware->alias([
             'role' => \App\Http\Middleware\CheckRole::class,
             'branch.scope' => \App\Http\Middleware\EnsureBranchScope::class,
             'branch.access' => \App\Http\Middleware\EnsureBranchAccess::class,
         ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->reportable(function (Throwable $e) {
            $request = request();
            $route = $request->route();
            Log::info('REAL_EXCEPTION_DEBUG', [
                'exception_class'  => get_class($e),
                'message'          => $e->getMessage(),
                'request_url'      => $request->fullUrl(),
                'route_name'       => optional($route)->getName(),
                'route_action'     => optional($route)->getActionName(),
                'middleware'       => optional($route)->gatherMiddleware() ?? [],
                'user_id'          => auth()->id(),
                'session_id'       => $request->hasSession() ? $request->session()->getId() : null,
                'request_method'   => $request->method(),
                'referer'          => $request->headers->get('referer'),
                'previous_url'     => $request->headers->get('referer'),
                'stack_trace'      => collect(explode("\n", $e->getTraceAsString()))->take(15)->toArray(),
            ]);
        });

        $exceptions->render(function (TokenMismatchException $e, Request $request) {
            if ($request->routeIs('logout') || $request->is('logout')) {
                return redirect()->route('login');
            }
        });
    })->create();
