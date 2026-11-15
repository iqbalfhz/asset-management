<?php

namespace App\Filament\Resources\Licenses\Pages;

use App\Filament\Actions\DomainAction;
use App\Filament\Resources\Licenses\LicenseResource;
use App\Models\License;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditLicense extends EditRecord
{
    protected static string $resource = LicenseResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            // Lisensi yang pernah dipakai tetap disimpan untuk riwayat seat.
            DeleteAction::make()
                ->visible(fn (License $record): bool => ! $record->seats()->withoutGlobalScopes()->exists()),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        return DomainAction::attempt(
            fn (): Model => parent::handleRecordUpdate($record, $data),
            __('Lisensi belum disimpan'),
            fn () => $this->halt(),
        );
    }
}
