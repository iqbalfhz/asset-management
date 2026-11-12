<?php

namespace App\Filament\Resources\Audits\RelationManagers;

use App\Enums\AuditResult;
use App\Filament\Exports\AuditItemExporter;
use App\Models\AuditItem;
use App\Models\Status;
use Filament\Actions\Action;
use Filament\Actions\ExportAction;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use LogicException;

class ItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'items';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('Hasil per aset');
    }

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        $canUpdate = fn (): bool => auth()->user()?->can('update', $this->getOwnerRecord()) ?? false;

        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['asset.status', 'recordedRoom', 'scanner']))
            ->defaultSort('id')
            ->columns([
                TextColumn::make('asset.kode')
                    ->label(__('Aset'))
                    ->description(fn (AuditItem $record): string => $record->asset->nama)
                    ->searchable(),
                TextColumn::make('hasil')
                    ->badge(),
                TextColumn::make('recordedRoom.nama')
                    ->label(__('Tercatat di'))
                    ->placeholder('—'),
                TextColumn::make('discan_pada')
                    ->label(__('Discan'))
                    ->dateTime('j M Y, H:i')
                    ->placeholder('—'),
                TextColumn::make('scanner.name')
                    ->label(__('Oleh'))
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('dipindahkan_pada')
                    ->label(__('Lokasi diperbarui'))
                    ->dateTime('j M Y, H:i')
                    ->placeholder('—')
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('hasil')
                    ->options(AuditResult::class),
            ])
            ->headerActions([
                ExportAction::make()
                    ->exporter(AuditItemExporter::class),
            ])
            ->recordActions([
                Action::make('move')
                    ->label(__('Pindahkan ke ruang ini'))
                    ->icon(Heroicon::OutlinedArrowsRightLeft)
                    ->visible(fn (AuditItem $record): bool => $record->hasil === AuditResult::SalahTempat && $record->dipindahkan_pada === null && $canUpdate())
                    ->requiresConfirmation()
                    ->modalDescription(fn (): string => __('Lokasi aset dicatat di :ruang.', ['ruang' => $this->getOwnerRecord()->room->nama]))
                    ->action(fn (AuditItem $record, Action $action) => static::run($action, fn () => $record->moveToAuditRoom(), __('Lokasi aset diperbarui'))),
                Action::make('markLost')
                    ->label(__('Tandai hilang'))
                    ->icon(Heroicon::OutlinedExclamationTriangle)
                    ->color('danger')
                    ->visible(fn (AuditItem $record): bool => $record->hasil === AuditResult::TidakDitemukan
                        && ! in_array($record->asset->status->nama, [Status::HILANG, Status::DIHAPUSKAN], true)
                        && $canUpdate())
                    ->requiresConfirmation()
                    ->modalDescription(__('Status aset menjadi Hilang, lalu aset bisa diajukan untuk penghapusan.'))
                    ->action(fn (AuditItem $record, Action $action) => static::run($action, fn () => $record->markLost(), __('Aset ditandai hilang'))),
            ]);
    }

    private static function run(Action $action, callable $transaction, string $successTitle): void
    {
        try {
            $transaction();
        } catch (LogicException $exception) {
            Notification::make()->danger()->title(__('Aksi dibatalkan'))->body($exception->getMessage())->send();
            $action->halt();
        }

        Notification::make()->success()->title($successTitle)->send();
    }
}
