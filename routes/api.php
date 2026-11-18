<?php

use App\Http\Controllers\Api\V1\AssetController;
use Illuminate\Support\Facades\Route;

// Batas laju dulu, supaya percobaan token tanpa akun ikut dibatasi per IP.
Route::prefix('v1')->middleware(['throttle:api', 'auth:sanctum'])->group(function () {
    Route::get('assets', [AssetController::class, 'index'])->middleware('abilities:baca-aset');
    Route::get('assets/{asset:kode}', [AssetController::class, 'show'])->middleware('abilities:baca-aset');
    Route::post('assets/{asset:kode}/serah-terima', [AssetController::class, 'handOver'])->middleware('abilities:serah-terima');
});
