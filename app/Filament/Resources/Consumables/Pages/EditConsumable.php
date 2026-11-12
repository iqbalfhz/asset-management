<?php

namespace App\Filament\Resources\Consumables\Pages;

use App\Filament\Resources\Consumables\ConsumableResource;
use App\Models\Consumable;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditConsumable extends EditRecord
{
    protected static string $resource = ConsumableResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            // Barang yang punya riwayat stok tetap disimpan.
            DeleteAction::make()
                ->visible(fn (Consumable $record): bool => ! $record->transactions()->withoutGlobalScopes()->exists()),
        ];
    }
}
