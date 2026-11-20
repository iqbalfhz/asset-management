<?php

use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\SetUserLocale;
use App\Models\ErrorLog;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Laravel\Sanctum\Http\Middleware\CheckAbilities;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Hanya alamat privat Cloudflare Tunnel yang dipercaya, supaya IP klien tidak bisa dipalsukan lewat header.
        $middleware->trustProxies(at: ['127.0.0.1', '10.0.0.0/8', '172.16.0.0/12', '192.168.0.0/16']);

        // Selain domain APP_URL ditolak, supaya tautan di email tidak bisa diarahkan ke host palsu; localhost untuk healthcheck.
        $middleware->trustHosts(at: ['^localhost$', '^127\.0\.0\.1$']);

        $middleware->append(SecurityHeaders::class);

        // Request Livewire dan halaman di luar panel (panduan) ikut bahasa user.
        $middleware->web(append: SetUserLocale::class);

        $middleware->alias(['abilities' => CheckAbilities::class]);

        // Halaman masuk ada di panel auth; tanpa ini, tamu di route API dari browser memicu error 500.
        $middleware->redirectGuestsTo(fn (): string => route('filament.auth.auth.login'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Error yang dilaporkan ikut tercatat untuk widget Kesehatan sistem; 404, validasi, dan akses ditolak tidak dilaporkan.
        $exceptions->report(fn (Throwable $exception) => ErrorLog::record($exception));

        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
