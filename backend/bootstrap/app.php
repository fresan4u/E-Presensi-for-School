<?php

use Illuminate\Auth\AuthenticationException;
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
        /*
         * Aplikasi ini tidak punya route `login` (autentikasi lewat Sanctum).
         * Tanpa callback ini, request API tanpa header `Accept: application/json`
         * (mis. lewat proxy Nitro/Vercel) memicu `route('login')` dan berakhir
         * 500. Untuk path API, redirect dikosongkan agar handler exception
         * mengembalikan 401 JSON.
         */
        $middleware->redirectGuestsTo(
            fn (Request $request) => $request->is('api/*') ? null : '/',
        );
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        /*
         * Pesan 401 bawaan Laravel berbahasa Inggris ("Unauthenticated.").
         * Ganti dengan pesan Indonesia supaya bisa langsung ditampilkan di UI.
         */
        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'message' => 'Sesi Anda sudah berakhir. Silakan login kembali.',
                ], 401);
            }
        });
    })->create();
