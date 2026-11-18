<?php

namespace App\Filament\Resources\Accessories\Pages;

use App\Filament\Resources\Accessories\AccessoryResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListAccessories extends ListRecords
{
    protected static string $resource = AccessoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
