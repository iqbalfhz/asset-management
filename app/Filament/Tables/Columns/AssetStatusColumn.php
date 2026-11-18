<?php

namespace App\Filament\Tables\Columns;

use App\Filament\Support\BadgeColor;
use App\Models\Asset;
use App\Models\Status;
use Filament\Tables\Columns\TextColumn;

class AssetStatusColumn
{
    // Badge status aset dengan warnanya; nama status inti ikut bahasa user.
    public static function make(): TextColumn
    {
        return TextColumn::make('status.nama')
            ->label(__('Status'))
            ->badge()
            ->formatStateUsing(fn (string $state): string => Status::labelFor($state))
            ->color(fn (Asset $record): ?array => BadgeColor::fromHex($record->status->warna));
    }
}
