<?php

use App\Http\Controllers\ScanAssetController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Panduan karyawan dibaca sebelum login (memasang 2FA), jadi tanpa autentikasi.
Route::view('/panduan', 'filament.panduan')->name('panduan');

// Tujuan QR di label aset; hanya mengarahkan ke panel, detail aset dibuka di dalam panel.
Route::get('/a/{kode}', ScanAssetController::class)->where('kode', '[A-Za-z0-9-]+')->name('asset.scan');
