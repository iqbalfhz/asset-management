<?php

namespace App\Filament\Resources\Accessories\Pages;

use App\Filament\Resources\Accessories\AccessoryResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewAccessory extends ViewRecord
{
    protected static string $resource = AccessoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
            DeleteAction::make(),
        ];
    }
}
