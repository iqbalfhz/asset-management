<?php

use App\Filament\Widgets\SystemHealth;
use App\Http\Middleware\LogDownload;
use App\Models\ErrorLog;
use App\Models\LoginLog;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schedule;

// Tanpa container worker: antrean (import, export, backup) diproses oleh schedule:run tiap menit dari Scheduled Task Coolify.
Schedule::command('queue:work --stop-when-empty --max-time=55 --tries=3 --timeout=900')
    ->everyMinute()
    ->withoutOverlapping(20)
    ->runInBackground();

// Backup malam hari (waktu Asia/Jakarta), di latar belakang supaya tidak terpotong batas waktu task Coolify.
Schedule::command('backup:clean')->dailyAt('01:00')->onOneServer()->runInBackground();
Schedule::command('backup:run')->dailyAt('01:30')->onOneServer()->runInBackground();
Schedule::command('backup:monitor')->dailyAt('03:00')->onOneServer();

// Ringkasan email sebelum jam kantor (waktu Asia/Jakarta), ke user yang jadwal pilihannya jatuh hari itu.
Schedule::command('notifikasi:ringkasan')->dailyAt('07:00')->onOneServer();

// Email pengingat tanggal penting aset (pajak/STNK, servis, kontrak sewa) H-30 dan H-7.
Schedule::command('pengingat:kirim')->dailyAt('07:00')->onOneServer();

// Detak untuk widget Kesehatan sistem: kalau scheduler berhenti, antrean dan backup ikut berhenti tanpa ada yang tahu.
Schedule::call(fn () => Cache::forever(SystemHealth::HEARTBEAT, now()->getTimestamp()))->everyMinute()->name('detak-scheduler');

// Log masuk disimpan satu tahun, error 90 hari (prunable di masing-masing model).
Schedule::command('model:prune', ['--model' => [LoginLog::class, ErrorLog::class]])->dailyAt('02:00')->onOneServer();

// Log unduhan disimpan dua tahun untuk investigasi; log perubahan data tidak ikut dibersihkan.
Schedule::command('activitylog:clean', [LogDownload::LOG, '--days' => 730, '--force'])->dailyAt('02:00')->onOneServer();

// Riwayat batch job import/export yang sudah selesai tidak perlu disimpan lama.
Schedule::command('queue:prune-batches --hours=168')->daily();
Schedule::command('sanctum:prune-expired --hours=24')->daily();
