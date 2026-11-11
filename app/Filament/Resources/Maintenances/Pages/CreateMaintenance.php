<?php

namespace App\Filament\Resources\Maintenances\Pages;

use App\Filament\Resources\Maintenances\MaintenanceResource;
use App\Models\Asset;
use App\Models\Maintenance;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use LogicException;

class CreateMaintenance extends CreateRecord
{
    protected static string $resource = MaintenanceResource::class;

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        try {
            return Maintenance::startDirect(Asset::query()->findOrFail($data['asset_id']), $data);
        } catch (LogicException $exception) {
            Notification::make()->danger()->title(__('Perbaikan belum dibuat'))->body($exception->getMessage())->send();
            $this->halt();
        }
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('index');
    }
}
