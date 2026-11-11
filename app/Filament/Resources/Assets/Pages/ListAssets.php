<?php

namespace App\Filament\Resources\Assets\Pages;

use App\Filament\Exports\AssetExporter;
use App\Filament\Imports\AssetImporter;
use App\Filament\Resources\Assets\AssetResource;
use App\Models\Asset;
use Filament\Actions\CreateAction;
use Filament\Actions\ExportAction;
use Filament\Actions\ImportAction;
use Filament\Resources\Pages\ListRecords;

class ListAssets extends ListRecords
{
    protected static string $resource = AssetResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Export mengikuti filter dan pencarian tabel, jadi berfungsi sebagai laporan per lokasi, kategori, status, atau pemegang.
            ExportAction::make()
                ->exporter(AssetExporter::class),
            ImportAction::make()
                ->importer(AssetImporter::class)
                ->authorize('create', Asset::class),
            CreateAction::make(),
        ];
    }
}
