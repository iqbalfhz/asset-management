<?php

namespace App\Filament\Resources\Audits\Pages;

use App\Enums\AuditStatus;
use App\Filament\Resources\Audits\AuditResource;
use App\Models\Audit;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;
use LogicException;

class ViewAudit extends ViewRecord
{
    protected static string $resource = AuditResource::class;

    protected function getHeaderActions(): array
    {
        $isRunning = fn (Audit $record): bool => $record->status === AuditStatus::Berjalan && (auth()->user()?->can('update', $record) ?? false);

        return [
            Action::make('scan')
                ->label(__('Scan'))
                ->icon(Heroicon::OutlinedQrCode)
                ->visible($isRunning)
                ->url(fn (Audit $record): string => AuditResource::getUrl('scan', ['record' => $record])),
            Action::make('close')
                ->label(__('Tutup sesi'))
                ->icon(Heroicon::OutlinedLockClosed)
                ->color('gray')
                ->visible($isRunning)
                ->requiresConfirmation()
                ->modalDescription(__('Aset yang belum discan ditandai Tidak ditemukan, dan aset yang ditemukan mendapat jadwal audit berikutnya. Sesi yang ditutup tidak bisa dibuka lagi.'))
                ->modalSubmitActionLabel(__('Tutup sesi'))
                ->action(function (Audit $record, Action $action): void {
                    try {
                        $record->close();
                    } catch (LogicException $exception) {
                        Notification::make()->danger()->title(__('Aksi dibatalkan'))->body($exception->getMessage())->send();
                        $action->halt();
                    }

                    Notification::make()->success()->title(__('Sesi ditutup'))->send();
                    // Muat ulang supaya ringkasan dan daftar hasil ikut berubah.
                    $this->redirect(AuditResource::getUrl('view', ['record' => $record]));
                }),
            Action::make('report')
                ->label(__('Laporan'))
                ->icon(Heroicon::OutlinedDocumentArrowDown)
                ->color('gray')
                ->url(fn (Audit $record): string => route('filament.admin.audits.report', $record))
                ->openUrlInNewTab(),
        ];
    }
}
