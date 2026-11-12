<?php

namespace App\Filament\Resources\Assets\RelationManagers;

use App\Filament\Resources\Licenses\LicenseResource;
use App\Filament\Resources\Licenses\RelationManagers\SeatsRelationManager;
use App\Models\LicenseSeat;
use Filament\Actions\Action;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class LicenseSeatsRelationManager extends RelationManager
{
    protected static string $relationship = 'licenseSeats';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('Lisensi');
    }

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('license'))
            ->defaultSort('dipasang_pada', 'desc')
            ->recordUrl(fn (LicenseSeat $record): string => LicenseResource::getUrl('view', ['record' => $record->license_id]))
            ->columns([
                TextColumn::make('license.nama_software')
                    ->label(__('Software')),
                TextColumn::make('dipasang_pada')
                    ->label(__('Dipasang'))
                    ->dateTime('j M Y, H:i'),
                TextColumn::make('dilepas_pada')
                    ->label(__('Dilepas'))
                    ->dateTime('j M Y, H:i')
                    ->placeholder(__('Masih dipakai')),
            ])
            ->filters([
                Filter::make('aktif')
                    ->label(__('Hanya yang masih dipakai'))
                    ->toggle()
                    ->default()
                    ->query(fn (Builder $query): Builder => $query->whereNull('dilepas_pada')),
            ])
            ->recordActions([
                Action::make('release')
                    ->label(__('Lepas'))
                    ->icon(Heroicon::OutlinedArrowUturnLeft)
                    ->color('gray')
                    ->visible(fn (LicenseSeat $record): bool => $record->dilepas_pada === null && (auth()->user()?->can('update', $record->license) ?? false))
                    ->requiresConfirmation()
                    ->modalDescription(__('Seat dilepas dan bisa dipakai lagi. Hapus juga instalasinya di aset ini.'))
                    ->action(fn (LicenseSeat $record, Action $action) => SeatsRelationManager::run($action, fn () => $record->release(), __('Seat dilepas'))),
            ]);
    }
}
