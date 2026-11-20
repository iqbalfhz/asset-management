<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Assets\AssetResource;
use App\Filament\Widgets\Concerns\FiltersByDashboard;
use App\Models\AssetReminder;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class RemindersDue extends TableWidget
{
    use FiltersByDashboard;

    protected static ?int $sort = 11;

    public function table(Table $table): Table
    {
        return $table
            ->heading(__('Pengingat jatuh tempo dalam :hari hari', ['hari' => AssetReminder::WARNING_DAYS]))
            ->query($this->filterByAsset(AssetReminder::query()->dueBy(today()->addDays(AssetReminder::WARNING_DAYS))->with('asset')))
            ->defaultSort('tanggal')
            ->paginated([5])
            ->emptyStateHeading(__('Tidak ada pengingat yang segera jatuh tempo'))
            ->recordUrl(fn (AssetReminder $record): string => AssetResource::getUrl('view', ['record' => $record->asset]))
            ->columns([
                TextColumn::make('jenis')
                    ->label(__('Jenis'))
                    ->sortable(),
                TextColumn::make('asset.kode')
                    ->label(__('Kode'))
                    ->sortable(),
                TextColumn::make('asset.nama')
                    ->label(__('Nama'))
                    ->sortable(),
                TextColumn::make('tanggal')
                    ->label(__('Jatuh tempo'))
                    ->sortable()
                    ->date('j M Y')
                    ->color(fn (AssetReminder $record): ?string => $record->isOverdue() ? 'danger' : null),
            ]);
    }
}
