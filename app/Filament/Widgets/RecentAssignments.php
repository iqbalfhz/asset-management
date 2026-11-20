<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\FiltersByDashboard;
use App\Models\Assignment;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class RecentAssignments extends TableWidget
{
    use FiltersByDashboard;

    protected static ?int $sort = 10;

    public function table(Table $table): Table
    {
        return $table
            ->heading(__('Serah-terima terbaru'))
            ->query($this->filterByAsset(Assignment::query()->with('asset')))
            ->defaultSort('id', 'desc')
            ->paginated([5])
            ->emptyStateHeading(__('Belum ada serah-terima'))
            ->columns([
                TextColumn::make('tanggal')
                    ->label(__('Tanggal'))
                    ->sortable()
                    ->date('j M Y'),
                TextColumn::make('asset.kode')
                    ->label(__('Aset'))
                    ->sortable()
                    ->description(fn (Assignment $record): string => $record->asset->nama),
                TextColumn::make('jenis')
                    ->label(__('Jenis'))
                    ->sortable()
                    ->badge(),
                TextColumn::make('pemegang')
                    ->label(__('Pemegang'))
                    ->state(fn (Assignment $record): string => $record->targetLabel()),
                TextColumn::make('status')
                    ->label(__('Status'))
                    ->sortable()
                    ->badge(),
            ]);
    }
}
