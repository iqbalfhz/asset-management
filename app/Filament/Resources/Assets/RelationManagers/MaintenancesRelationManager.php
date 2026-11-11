<?php

namespace App\Filament\Resources\Assets\RelationManagers;

use App\Filament\Resources\Maintenances\MaintenanceResource;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class MaintenancesRelationManager extends RelationManager
{
    protected static string $relationship = 'maintenances';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('Perbaikan');
    }

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return MaintenanceResource::table($table);
    }
}
