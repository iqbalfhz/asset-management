<?php

namespace App\Filament\Resources\Assets\Pages;

use App\Filament\Resources\Assets\Actions\AssetTransactionActions;
use App\Filament\Resources\Assets\Actions\PrintLabelActions;
use App\Filament\Resources\Assets\AssetResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewAsset extends ViewRecord
{
    protected static string $resource = AssetResource::class;

    protected function getHeaderActions(): array
    {
        return [
            AssetTransactionActions::handOver(),
            AssetTransactionActions::takeBack(),
            AssetTransactionActions::recover(),
            PrintLabelActions::single(),
            EditAction::make(),
        ];
    }
}
