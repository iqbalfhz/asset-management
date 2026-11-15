<?php

namespace App\Filament\Tables\Columns;

use Filament\Tables\Columns\TextColumn;

class CompanyColumn
{
    // Pasangan CompanySelect di tabel: kode perusahaan, tersembunyi kecuali dibuka (umumnya hanya satu PT).
    public static function make(): TextColumn
    {
        return TextColumn::make('company.kode')
            ->label(__('Perusahaan'))
            ->toggleable(isToggledHiddenByDefault: true);
    }
}
