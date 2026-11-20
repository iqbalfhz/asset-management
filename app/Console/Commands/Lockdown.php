<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\PersonalAccessToken;

// Langkah darurat saat diduga diretas: usir semua sesi dan token, opsional bekukan akun. Super admin tetap aman.
class Lockdown extends Command
{
    protected $signature = 'lockdown
        {--accounts : Nonaktifkan juga semua akun non-super-admin}
        {--lift : Aktifkan kembali akun yang tadi dikunci lockdown}
        {--force : Jalankan tanpa konfirmasi}';

    protected $description = 'Kunci sistem saat darurat: logout semua sesi, cabut semua token API, opsional bekukan akun';

    private const RECORD = 'lockdown-accounts.json';

    public function handle(): int
    {
        if ($this->option('lift')) {
            return $this->lift();
        }

        if (! $this->option('force') && ! $this->confirm('Kunci sistem sekarang? Semua orang akan logout'.($this->option('accounts') ? ' dan semua akun non-super-admin dibekukan' : '').'.')) {
            $this->info('Dibatalkan.');

            return self::SUCCESS;
        }

        $sessions = DB::table('sessions')->delete();
        $tokens = PersonalAccessToken::query()->delete();
        $this->line("Sesi dihapus: {$sessions}");
        $this->line("Token API dicabut: {$tokens}");

        if ($this->option('accounts')) {
            $ids = User::query()
                ->where('is_active', true)
                ->whereDoesntHave('roles', fn ($query) => $query->where('name', 'super_admin'))
                ->pluck('id');

            User::query()->whereKey($ids)->update(['is_active' => false]);
            Storage::disk('local')->put(self::RECORD, $ids->toJson());
            $this->line("Akun dibekukan: {$ids->count()} (super admin tetap aktif)");
        }

        $this->newLine();
        $this->info('Sistem terkunci. Langkah berikutnya: ganti password admin, nyalakan 2FA, periksa Log masuk dan Log aktivitas.');
        $this->comment('Buka kembali akun dengan: php artisan lockdown --lift');

        return self::SUCCESS;
    }

    private function lift(): int
    {
        if (! Storage::disk('local')->exists(self::RECORD)) {
            $this->warn('Tidak ada catatan akun yang dikunci lockdown. Tidak ada yang diaktifkan.');

            return self::SUCCESS;
        }

        $ids = collect(json_decode(Storage::disk('local')->get(self::RECORD), true) ?? []);
        User::query()->whereKey($ids)->update(['is_active' => true]);
        Storage::disk('local')->delete(self::RECORD);

        $this->info("Akun diaktifkan kembali: {$ids->count()}. Sesi dan token yang lama tetap tercabut, jadi semua login ulang.");

        return self::SUCCESS;
    }
}
