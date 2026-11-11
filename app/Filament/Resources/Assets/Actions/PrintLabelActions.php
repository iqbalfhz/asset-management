<?php

namespace App\Filament\Resources\Assets\Actions;

use App\Models\Asset;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Component;

// Cetak label QR/barcode: satu aset (tab baru) atau banyak sekaligus.
class PrintLabelActions
{
    public const int MAX_LABELS = 500;

    public static function single(): Action
    {
        return Action::make('label')
            ->label(__('Label'))
            ->icon(Heroicon::OutlinedQrCode)
            ->color('gray')
            ->url(fn (Asset $record): string => route('filament.admin.labels', ['aset' => $record->getKey()]))
            ->openUrlInNewTab();
    }

    public static function bulk(): BulkAction
    {
        return BulkAction::make('printLabels')
            ->label(__('Cetak label'))
            ->icon(Heroicon::OutlinedQrCode)
            ->action(function (Collection $records, Component $livewire, BulkAction $action): void {
                if ($records->count() > static::MAX_LABELS) {
                    Notification::make()
                        ->danger()
                        ->title(__('Terlalu banyak label'))
                        ->body(__('Pilih paling banyak :max aset sekali cetak.', ['max' => static::MAX_LABELS]))
                        ->send();
                    $action->halt();
                }

                $livewire->redirect(route('filament.admin.labels', ['aset' => implode(',', $records->modelKeys())]));
            });
    }
}
