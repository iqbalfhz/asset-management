<?php

namespace App\Filament\Resources\Assets\Pages;

use App\Filament\Imports\AssetImporter;
use App\Filament\Resources\Assets\AssetResource;
use App\Models\Asset;
use Filament\Actions\CreateAction;
use Filament\Actions\ImportAction;
use Filament\Resources\Pages\ListRecords;

class ListAssets extends ListRecords
{
    protected static string $resource = AssetResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ImportAction::make()
                ->importer(AssetImporter::class)
                ->authorize('create', Asset::class),
            CreateAction::make(),
        ];
    }
}
