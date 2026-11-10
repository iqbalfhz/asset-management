<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

#[Fillable(['company_id', 'key', 'value'])]
class Setting extends Model
{
    /**
     * Baca pengaturan global (companyId null) atau milik satu perusahaan.
     */
    public static function get(string $key, mixed $default = null, ?int $companyId = null): mixed
    {
        // Dibungkus array supaya nilai kosong juga ikut di-cache; memo menahan hasilnya selama satu request.
        $cached = Cache::memo()->rememberForever(
            static::cacheKey($key, $companyId),
            fn (): array => ['value' => static::query()->where('company_id', $companyId)->where('key', $key)->value('value')],
        );

        return $cached['value'] ?? $default;
    }

    public static function put(string $key, mixed $value, ?int $companyId = null): void
    {
        static::query()->updateOrCreate(['company_id' => $companyId, 'key' => $key], ['value' => $value]);

        Cache::memo()->forget(static::cacheKey($key, $companyId));
    }

    private static function cacheKey(string $key, ?int $companyId): string
    {
        return 'settings.'.($companyId ?? 'global').'.'.$key;
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'value' => 'json',
        ];
    }
}
