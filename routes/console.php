<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Di server tidak ada container worker: Scheduled Task Coolify menjalankan `php artisan schedule:run` tiap menit,
// dan antrean (import, export, backup dari panel) diproses di latar belakang sampai kosong.
Schedule::command('queue:work --stop-when-empty --max-time=55 --tries=3 --timeout=900')
    ->everyMinute()
    ->withoutOverlapping(20)
    ->runInBackground();

// Backup malam hari (waktu Asia/Jakarta), di latar belakang supaya tidak terpotong batas waktu task Coolify.
Schedule::command('backup:clean')->dailyAt('01:00')->onOneServer()->runInBackground();
Schedule::command('backup:run')->dailyAt('01:30')->onOneServer()->runInBackground();
Schedule::command('backup:monitor')->dailyAt('03:00')->onOneServer();

// Riwayat batch job import/export yang sudah selesai tidak perlu disimpan lama.
Schedule::command('queue:prune-batches --hours=168')->daily();
