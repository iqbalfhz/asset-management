<?php

namespace App\Filament\Resources\Disposals\Pages;

use App\Filament\Actions\DomainAction;
use App\Filament\Resources\Disposals\DisposalResource;
use App\Models\Asset;
use App\Models\Disposal;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateDisposal extends CreateRecord
{
    protected static string $resource = DisposalResource::class;

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        return DomainAction::attempt(
            fn (): Disposal => Disposal::submit(Asset::query()->findOrFail($data['asset_id']), $data),
            __('Ajuan belum dibuat'),
            fn () => $this->halt(),
        );
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('index');
    }
}
