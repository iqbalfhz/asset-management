<?php

namespace App\Filament\Resources\Licenses\Pages;

use App\Filament\Exports\LicenseExporter;
use App\Filament\Imports\LicenseImporter;
use App\Filament\Resources\Licenses\LicenseResource;
use App\Models\License;
use Filament\Actions\CreateAction;
use Filament\Actions\ExportAction;
use Filament\Actions\ImportAction;
use Filament\Resources\Pages\ListRecords;

class ListLicenses extends ListRecords
{
    protected static string $resource = LicenseResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ImportAction::make()
                ->importer(LicenseImporter::class)
                ->modalDescription(__('Jangan sertakan product key di file ini. Isi product key lewat form lisensi, supaya tersimpan terenkripsi.'))
                ->authorize('create', License::class),
            ExportAction::make()
                ->exporter(LicenseExporter::class),
            CreateAction::make(),
        ];
    }
}
