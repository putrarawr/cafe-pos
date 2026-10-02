<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->redirectGuestsTo('/kasir/login');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Hanya request AJAX kasir yang boleh dapat JSON error. Kalau $request->is('kasir/*')
        // tanpa expectsJson(), buka /kasir di browser saat belum login akan dirender
        // sebagai JSON {"message":"Unauthenticated."} dan override redirectGuestsTo
        // di atas, jadi kasir tidak pernah sampai ke halaman login.
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*')
                || ($request->is('kasir/*') && $request->expectsJson()),
        );
    })->create();
