<?php

namespace App\Filament\Widgets;

use App\Models\Assignment;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class RecentAssignments extends TableWidget
{
    protected static ?int $sort = 2;

    public function table(Table $table): Table
    {
        return $table
            ->heading(__('Serah-terima terbaru'))
            ->query(Assignment::query()->with('asset'))
            ->defaultSort('id', 'desc')
            ->paginated([5])
            ->emptyStateHeading(__('Belum ada serah-terima'))
            ->columns([
                TextColumn::make('tanggal')
                    ->date('j M Y'),
                TextColumn::make('asset.kode')
                    ->label(__('Aset'))
                    ->description(fn (Assignment $record): string => $record->asset->nama),
                TextColumn::make('jenis')
                    ->badge(),
                TextColumn::make('pemegang')
                    ->label(__('Pemegang'))
                    ->state(fn (Assignment $record): string => $record->targetLabel()),
                TextColumn::make('status')
                    ->badge(),
            ]);
    }
}
