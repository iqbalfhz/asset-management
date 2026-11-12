<?php

namespace App\Filament\Resources\Employees\RelationManagers;

use App\Filament\Resources\Licenses\LicenseResource;
use App\Models\LicenseSeat;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class LicenseSeatsRelationManager extends RelationManager
{
    protected static string $relationship = 'licenseSeats';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('Seat lisensi');
    }

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('license')->whereNull('dilepas_pada'))
            ->defaultSort('dipasang_pada', 'desc')
            ->recordUrl(fn (LicenseSeat $record): string => LicenseResource::getUrl('view', ['record' => $record->license_id]))
            ->emptyStateHeading(__('Tidak memakai seat lisensi'))
            ->columns([
                TextColumn::make('license.nama_software')
                    ->label(__('Software')),
                TextColumn::make('license.tgl_habis')
                    ->label(__('Lisensi habis'))
                    ->date('j M Y')
                    ->placeholder(__('Tidak ada batas')),
                TextColumn::make('dipasang_pada')
                    ->label(__('Dipasang'))
                    ->dateTime('j M Y, H:i'),
            ]);
    }
}
