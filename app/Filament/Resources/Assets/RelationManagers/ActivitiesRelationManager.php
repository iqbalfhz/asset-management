<?php

namespace App\Filament\Resources\Assets\RelationManagers;

use App\Filament\Tables\Columns\ActivityColumns;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

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
                ActivityColumns::event(),
                TextColumn::make('causer.name')
                    ->label(__('Oleh'))
                    ->placeholder(__('Sistem')),
                ActivityColumns::changes(),
            ]);
    }
}
