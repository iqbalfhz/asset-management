<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Panduan karyawan dibaca sebelum login (memasang 2FA), jadi tanpa autentikasi.
Route::view('/panduan', 'filament.panduan')->name('panduan');
