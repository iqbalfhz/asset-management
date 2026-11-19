<?php

use App\Models\LoginLog;
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

// Log masuk disimpan satu tahun (LoginLog::prunable).
Schedule::command('model:prune', ['--model' => [LoginLog::class]])->dailyAt('02:00')->onOneServer();

// Riwayat batch job import/export yang sudah selesai tidak perlu disimpan lama.
Schedule::command('queue:prune-batches --hours=168')->daily();
Schedule::command('sanctum:prune-expired --hours=24')->daily();
