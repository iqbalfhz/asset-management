<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

// Dokumen resmi (BAST, berita acara, laporan, label) selalu berbahasa Indonesia (UU No. 24 Tahun 2009), apa pun bahasa user.
class UseIndonesianForDocuments
{
    public function handle(Request $request, Closure $next): Response
    {
        app()->setLocale('id');

        return $next($request);
    }
}
