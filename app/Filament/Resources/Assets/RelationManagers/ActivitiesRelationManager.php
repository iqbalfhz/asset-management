<?php

namespace App\Filament\Resources\Assets\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Models\Activity;

class ActivitiesRelationManager extends RelationManager
{
    protected static string $relationship = 'activitiesAsSubject';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('Log perubahan');
    }

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('causer'))
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('created_at')
                    ->label(__('Waktu'))
                    ->dateTime('j M Y H:i'),
                TextColumn::make('event')
                    ->label(__('Kejadian'))
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'created' => __('Dibuat'),
                        'updated' => __('Diubah'),
                        'deleted' => __('Dihapus'),
                        default => (string) $state,
                    }),
                TextColumn::make('causer.name')
                    ->label(__('Oleh'))
                    ->placeholder(__('Sistem')),
                TextColumn::make('perubahan')
                    ->label(__('Perubahan'))
                    ->state(fn (Activity $record): array => static::describeChanges($record))
                    ->listWithLineBreaks()
                    ->wrap(),
            ]);
    }

    /**
     * @return array<int, string>
     */
    private static function describeChanges(Activity $record): array
    {
        $new = data_get($record->attribute_changes, 'attributes', []);
        $old = data_get($record->attribute_changes, 'old', []);

        return collect($new)
            ->map(fn (mixed $value, string $column): string => array_key_exists($column, $old)
                ? "{$column}: ".static::show($old[$column]).' → '.static::show($value)
                : "{$column}: ".static::show($value))
            ->values()
            ->all();
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
