<?php

namespace App\Filament\Resources\Maintenances\Pages;

use App\Filament\Actions\DomainAction;
use App\Filament\Resources\Maintenances\MaintenanceResource;
use App\Models\Asset;
use App\Models\Maintenance;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateMaintenance extends CreateRecord
{
    protected static string $resource = MaintenanceResource::class;

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        return DomainAction::attempt(
            fn (): Maintenance => Maintenance::startDirect(Asset::query()->findOrFail($data['asset_id']), $data),
            __('Perbaikan belum dibuat'),
            fn () => $this->halt(),
        );
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('index');
    }
}
