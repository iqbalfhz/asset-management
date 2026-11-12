<?php

namespace App\Filament\Resources\Assets\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'auditItems';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('Stock opname');
    }

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            // Sesi dibaca tanpa scope role, supaya riwayat aset tetap utuh walau sesinya dibuat admin lain.
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with([
                'audit' => fn (BelongsTo $query) => $query->withoutGlobalScopes(),
                'audit.room',
                'scanner',
            ]))
            ->defaultSort('id', 'desc')
            ->emptyStateHeading(__('Belum pernah diaudit'))
            ->columns([
                TextColumn::make('audit.mulai_pada')
                    ->label(__('Sesi'))
                    ->date('j M Y'),
                TextColumn::make('audit.room.nama')
                    ->label(__('Ruang diaudit')),
                TextColumn::make('hasil')
                    ->badge(),
                TextColumn::make('discan_pada')
                    ->label(__('Discan'))
                    ->dateTime('j M Y, H:i')
                    ->placeholder('—'),
                TextColumn::make('scanner.name')
                    ->label(__('Oleh'))
                    ->placeholder('—'),
            ]);
    }
}
