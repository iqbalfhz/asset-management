<?php

namespace App\Filament\Resources\Assets\RelationManagers;

use App\Filament\Resources\Licenses\LicenseResource;
use App\Filament\Tables\LicenseSeatTable;
use App\Models\LicenseSeat;
use Filament\Resources\RelationManagers\RelationManager;
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
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['license', 'asset']))
            ->defaultSort('dipasang_pada', 'desc')
            ->recordUrl(fn (LicenseSeat $record): string => LicenseResource::getUrl('view', ['record' => $record->license_id]))
            ->columns([
                LicenseSeatTable::software(),
                LicenseSeatTable::installedAt(),
                LicenseSeatTable::releasedAt(),
            ])
            ->filters([
                LicenseSeatTable::activeOnlyFilter(),
            ])
            ->recordActions([
                LicenseSeatTable::releaseAction(),
            ]);
    }
}
