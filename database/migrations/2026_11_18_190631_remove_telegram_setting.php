<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Notifikasi Telegram tidak dipakai (keputusan 2026-11-18); saklarnya di Pengaturan ikut dihapus.
return new class extends Migration
{
    public function up(): void
    {
        DB::table('settings')->where('key', 'telegram_enabled')->delete();
    }
};
