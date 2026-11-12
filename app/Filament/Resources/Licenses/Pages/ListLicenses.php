<?php

namespace App\Filament\Resources\Licenses\Pages;

use App\Filament\Exports\LicenseExporter;
use App\Filament\Resources\Licenses\LicenseResource;
use Filament\Actions\CreateAction;
use Filament\Actions\ExportAction;
use Filament\Resources\Pages\ListRecords;

class ListLicenses extends ListRecords
{
    protected static string $resource = LicenseResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ExportAction::make()
                ->exporter(LicenseExporter::class),
            CreateAction::make(),
        ];
    }
}
