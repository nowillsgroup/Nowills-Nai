<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use App\Http\Middleware\EnsureRole;
use App\Http\Middleware\PreviewDemoEntry;
use App\Http\Middleware\SecurityHeaders;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias(['role' => EnsureRole::class]);
        $middleware->append(SecurityHeaders::class);
        // The local preview entry must run before auth so an opted-in demo
        // preview can open NAi directly even without an existing session.
        $middleware->prependToPriorityList(
            before: \Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests::class,
            prepend: PreviewDemoEntry::class,
        );
        // Arena's live preview terminates TLS at a reverse proxy on the
        // sandbox link-local/loopback network. Trust those forwarded headers
        // so generated URLs and cookies stay HTTPS inside the preview iframe.
        $middleware->trustProxies(at: ['127.0.0.1', '::1', '169.254.0.0/16']);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
