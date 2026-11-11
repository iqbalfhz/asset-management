<?php

namespace App\Filament\Resources\Disposals\Pages;

use App\Filament\Resources\Disposals\DisposalResource;
use App\Models\Asset;
use App\Models\Disposal;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use LogicException;

class CreateDisposal extends CreateRecord
{
    protected static string $resource = DisposalResource::class;

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        try {
            return Disposal::submit(Asset::query()->findOrFail($data['asset_id']), $data);
        } catch (LogicException $exception) {
            Notification::make()->danger()->title(__('Ajuan belum dibuat'))->body($exception->getMessage())->send();
            $this->halt();
        }
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('index');
    }
}
