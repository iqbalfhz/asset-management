<?php

namespace App\Filament\Resources\Consumables\Pages;

use App\Filament\Resources\Consumables\Actions\StockActions;
use App\Filament\Resources\Consumables\ConsumableResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewConsumable extends ViewRecord
{
    protected static string $resource = ConsumableResource::class;

    protected function getHeaderActions(): array
    {
        return [
            StockActions::receive(),
            StockActions::issue(),
            EditAction::make(),
        ];
    }
}
