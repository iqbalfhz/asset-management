<?php

namespace App\Filament\Tables\Columns;

use Filament\Tables\Columns\TextColumn;
use Spatie\Activitylog\Models\Activity;

// Kolom log aktivitas yang dipakai di tab Log perubahan aset dan halaman Log aktivitas.
class ActivityColumns
{
    public static function event(): TextColumn
    {
        return TextColumn::make('event')
            ->label(__('Kejadian'))
            ->badge()
            ->formatStateUsing(fn (?string $state): string => match ($state) {
                'created' => __('Dibuat'),
                'updated' => __('Diubah'),
                'deleted' => __('Dihapus'),
                'dinonaktifkan_paksa' => __('Nonaktif paksa'),
                'ditemukan_kembali' => __('Ditemukan kembali'),
                default => (string) $state,
            });
    }

    public static function changes(): TextColumn
    {
        return TextColumn::make('perubahan')
            ->label(__('Perubahan'))
            ->state(fn (Activity $record): array => static::describe($record))
            ->listWithLineBreaks()
            ->wrap();
    }

    /**
     * Daftar "kolom: lama → baru", ditambah keterangan log khusus (misalnya alasan nonaktif paksa).
     *
     * @return array<int, string>
     */
    public static function describe(Activity $record): array
    {
        $new = data_get($record->attribute_changes, 'attributes', []);
        $old = data_get($record->attribute_changes, 'old', []);

        $changes = collect($new)
            ->map(fn (mixed $value, string $column): string => array_key_exists($column, $old)
                ? "{$column}: ".static::show($old[$column]).' → '.static::show($value)
                : "{$column}: ".static::show($value));

        $properties = collect($record->properties ?? [])
            ->map(fn (mixed $value, string $key): string => "{$key}: ".static::show($value));

        return $changes->merge($properties)->values()->all();
    }

    private static function show(mixed $value): string
    {
        return match (true) {
            $value === null => '—',
            is_bool($value) => $value ? __('ya') : __('tidak'),
            is_array($value) => json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            default => (string) $value,
        };
    }
}
