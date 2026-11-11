<?php

namespace App\Filament\Resources\Audits\Tables;

use App\Enums\AuditResult;
use App\Enums\AuditStatus;
use App\Filament\Resources\Audits\AuditResource;
use App\Models\Audit;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class AuditsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query
                ->with(['room.floor.building', 'creator'])
                ->withCount([
                    'items',
                    'items as found_count' => fn (Builder $query) => $query->whereIn('hasil', AuditResult::found()),
                    'items as missing_count' => fn (Builder $query) => $query->where('hasil', AuditResult::TidakDitemukan),
                ]))
            ->defaultSort('mulai_pada', 'desc')
            ->columns([
                TextColumn::make('mulai_pada')
                    ->label(__('Dimulai'))
                    ->dateTime('j M Y, H:i')
                    ->sortable(),
                TextColumn::make('room.nama')
                    ->label(__('Ruang'))
                    ->description(fn (Audit $record): string => "{$record->room->floor->building->nama} — {$record->room->floor->nama}")
                    ->searchable(),
                TextColumn::make('status')
                    ->badge(),
                TextColumn::make('items_count')
                    ->label(__('Aset')),
                TextColumn::make('found_count')
                    ->label(__('Ditemukan')),
                TextColumn::make('missing_count')
                    ->label(__('Tidak ditemukan')),
                TextColumn::make('creator.name')
                    ->label(__('Dibuat oleh'))
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(AuditStatus::class),
            ])
            ->recordActions([
                Action::make('scan')
                    ->label(__('Scan'))
                    ->icon(Heroicon::OutlinedQrCode)
                    ->visible(fn (Audit $record): bool => $record->status === AuditStatus::Berjalan && (auth()->user()?->can('update', $record) ?? false))
                    ->url(fn (Audit $record): string => AuditResource::getUrl('scan', ['record' => $record])),
                ViewAction::make(),
            ]);
    }
}
