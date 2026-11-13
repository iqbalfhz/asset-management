<?php

namespace App\Filament\Resources\Rooms\Pages;

use App\Filament\Imports\LocationImporter;
use App\Filament\Resources\Rooms\RoomResource;
use App\Models\Building;
use App\Models\Room;
use Filament\Actions\CreateAction;
use Filament\Actions\ImportAction;
use Filament\Resources\Pages\ManageRecords;

class ManageRooms extends ManageRecords
{
    protected static string $resource = RoomResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ImportAction::make()
                ->label(__('Import lokasi'))
                ->importer(LocationImporter::class)
                // Import lokasi ikut membuat gedung dan lantai yang belum ada.
                ->authorize(fn (): bool => auth()->user()->can('create', Room::class) && auth()->user()->can('create', Building::class)),
            CreateAction::make(),
        ];
    }
}
