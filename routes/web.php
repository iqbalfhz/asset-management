<?php

use App\Http\Controllers\ScanAssetController;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Route;

// Tanpa landing page: tamu ke halaman masuk, user yang sudah masuk ke panel sesuai role-nya.
Route::get('/', function (): RedirectResponse {
    if (auth()->guest()) {
        return redirect()->route('filament.auth.auth.login');
    }

    $homePanel = auth()->user()->homePanel();

    abort_if($homePanel === null, 403);

    return redirect($homePanel->getUrl());
});

// Panduan karyawan dibaca sebelum login (memasang 2FA), jadi tanpa autentikasi.
Route::view('/panduan', 'filament.panduan')->name('panduan');

// Tujuan QR di label aset; hanya mengarahkan ke panel, detail aset dibuka di dalam panel.
Route::get('/a/{kode}', ScanAssetController::class)->where('kode', '[A-Za-z0-9-]+')->name('asset.scan');
