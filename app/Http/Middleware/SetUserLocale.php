<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

// Bahasa antarmuka mengikuti pilihan user di Profil; tamu (halaman masuk) memakai bahasa bawaan aplikasi.
class SetUserLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($user = $request->user()) {
            app()->setLocale($user->preferredLocale());
        }

        return $next($request);
    }
}
