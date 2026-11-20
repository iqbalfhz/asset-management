<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

#[Fillable(['sidik', 'jenis', 'pesan', 'lokasi', 'url', 'user_id', 'jumlah'])]
class ErrorLog extends Model
{
    use MassPrunable;

    /**
     * Error yang sama (jenis dan lokasi kode) digabung dan dihitung. Isi form, parameter URL, dan SQL tidak disimpan.
     */
    public static function record(Throwable $exception): void
    {
        try {
            $location = Str::after($exception->getFile(), base_path().DIRECTORY_SEPARATOR).':'.$exception->getLine();

            static::query()->upsert([[
                'sidik' => sha1($exception::class.'@'.$location),
                'jenis' => Str::limit($exception::class, 255, ''),
                'pesan' => Str::limit(Str::before($exception->getMessage(), ' (Connection:'), 1000),
                'lokasi' => Str::limit($location, 255, ''),
                'url' => request()->route() === null ? null : Str::limit(request()->path(), 255, ''),
                'user_id' => auth()->id(),
            ]], ['sidik'], ['pesan', 'url', 'user_id', 'jumlah' => DB::raw('jumlah + 1')]);
        } catch (Throwable) {
            // Database mati atau sejenisnya: jangan memicu error baru; log file tetap mencatat error aslinya.
        }
    }

    // Disimpan 90 hari sejak terakhir terjadi.
    public function prunable(): Builder
    {
        return static::query()->where('updated_at', '<', now()->subDays(90));
    }
}
