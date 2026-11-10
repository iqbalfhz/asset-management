<?php

namespace App\Filament\Resources\Depreciations\Pages;

use App\Filament\Resources\Depreciations\DepreciationResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageDepreciations extends ManageRecords
{
    protected static string $resource = DepreciationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
