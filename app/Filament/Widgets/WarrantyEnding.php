<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Assets\AssetResource;
use App\Models\Asset;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class WarrantyEnding extends TableWidget
{
    protected static ?int $sort = 3;

    public function table(Table $table): Table
    {
        return $table
            ->heading(__('Garansi habis dalam 60 hari'))
            ->query(AssetOverview::warrantyEndingSoon()->with('category'))
            ->defaultSort('garansi_sampai')
            ->paginated([5])
            ->emptyStateHeading(__('Tidak ada garansi yang segera habis'))
            ->recordUrl(fn (Asset $record): string => AssetResource::getUrl('edit', ['record' => $record]))
            ->columns([
                TextColumn::make('kode'),
                TextColumn::make('nama'),
                TextColumn::make('category.nama')
                    ->label(__('Kategori')),
                TextColumn::make('garansi_sampai')
                    ->label(__('Garansi sampai'))
                    ->date('j M Y'),
            ]);
    }
}
