<?php

use App\Exceptions\BookingConflictException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__.'/../routes/api.php',
        apiPrefix: '', // نسخه اصلی NestJS بدون global prefix بود: /bookings نه /api/bookings
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // معادل app.enableCors({ origin: '*' , methods: [...] }) در main.ts نسخه NestJS
        $middleware->api(prepend: [
            \Illuminate\Http\Middleware\HandleCors::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // معادل BookingConflictException (که در NestJS از ConflictException ارث‌بری
        // می‌کرد و خودکار HTTP 409 برمی‌گرداند)
        $exceptions->render(function (BookingConflictException $e, $request) {
            return response()->json(['message' => $e->getMessage()], 409);
        });
    })->create();
