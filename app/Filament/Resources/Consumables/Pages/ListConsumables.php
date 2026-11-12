<?php

namespace App\Filament\Resources\Consumables\Pages;

use App\Filament\Exports\ConsumableExporter;
use App\Filament\Exports\ConsumableTransactionExporter;
use App\Filament\Resources\Consumables\ConsumableResource;
use App\Models\ConsumableTransaction;
use Filament\Actions\CreateAction;
use Filament\Actions\ExportAction;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListConsumables extends ListRecords
{
    protected static string $resource = ConsumableResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ExportAction::make()
                ->label(__('Export stok'))
                ->exporter(ConsumableExporter::class),
            ExportAction::make('exportTransactions')
                ->label(__('Export riwayat'))
                ->exporter(ConsumableTransactionExporter::class)
                // Riwayat barang yang tampil di tabel (ikut filter dan pencarian), dalam rentang tanggal yang dipilih.
                ->modifyQueryUsing(fn (Builder $query, array $options): Builder => ConsumableTransactionExporter::modifyQuery(ConsumableTransaction::query())
                    ->whereIn('consumable_id', $query->reorder()->select('consumables.id'))
                    ->whereBetween('tanggal', [$options['dari'], $options['sampai']])),
            CreateAction::make(),
        ];
    }
}
