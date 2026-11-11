<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Backup malam hari (waktu Asia/Jakarta): bersihkan yang lama, buat yang baru, lalu periksa kesehatannya.
Schedule::command('backup:clean')->dailyAt('01:00')->onOneServer();
Schedule::command('backup:run')->dailyAt('01:30')->onOneServer();
Schedule::command('backup:monitor')->dailyAt('03:00')->onOneServer();

// Riwayat batch job import/export yang sudah selesai tidak perlu disimpan lama.
Schedule::command('queue:prune-batches --hours=168')->daily();
