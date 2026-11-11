<?php

namespace App\Filament\Resources\Audits\Pages;

use App\Filament\Resources\Audits\AuditResource;
use App\Models\Audit;
use App\Models\Room;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use LogicException;

class CreateAudit extends CreateRecord
{
    protected static string $resource = AuditResource::class;

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        try {
            return Audit::start(Room::query()->findOrFail($data['room_id']), $data['catatan'] ?? null);
        } catch (LogicException $exception) {
            Notification::make()->danger()->title(__('Sesi belum dibuat'))->body($exception->getMessage())->send();
            $this->halt();
        }
    }

    // Petugas biasanya langsung berkeliling, jadi setelah dibuat langsung ke halaman scan.
    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('scan', ['record' => $this->getRecord()]);
    }
}
