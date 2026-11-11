<?php

namespace App\Filament\Resources\Assets\Tables;

use App\Filament\Exports\AssetExporter;
use App\Filament\Resources\Assets\Actions\AssetTransactionActions;
use App\Filament\Resources\Assets\AssetResource;
use App\Models\Asset;
use App\Models\Assignment;
use App\Models\Employee;
use App\Models\Room;
use App\Models\Status;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\EditAction;
use Filament\Actions\ExportBulkAction;
use Filament\Actions\ReplicateAction;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Support\Colors\Color;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use LogicException;

class AssetsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['category', 'status', 'room.floor.building', 'assetModel.manufacturer', 'assigned', 'depreciation']))
            ->columns([
                TextColumn::make('kode')
                    ->searchable()
                    ->sortable()
                    ->copyable(),
                TextColumn::make('nama')
                    ->searchable(),
                TextColumn::make('category.nama')
                    ->label(__('Kategori'))
                    ->badge()
                    ->color(fn (Asset $record): ?array => $record->category->warna ? Color::hex($record->category->warna) : null),
                TextColumn::make('status.nama')
                    ->label(__('Status'))
                    ->badge()
                    ->color(fn (Asset $record): ?array => $record->status->warna ? Color::hex($record->status->warna) : null),
                TextColumn::make('pemegang')
                    ->label(__('Pemegang'))
                    ->state(fn (Asset $record): ?string => $record->assigned ? Assignment::labelFor($record->assigned) : null)
                    ->placeholder('—'),
                TextColumn::make('room.nama')
                    ->label(__('Lokasi'))
                    ->description(fn (Asset $record): ?string => $record->room ? "{$record->room->floor->building->nama} — {$record->room->floor->nama}" : null),
                TextColumn::make('assetModel.nama')
                    ->label(__('Model'))
                    ->formatStateUsing(fn (Asset $record): string => "{$record->assetModel->manufacturer->nama} {$record->assetModel->nama}")
                    ->toggleable(),
                TextColumn::make('no_seri')
                    ->label(__('Nomor seri'))
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('order_number')
                    ->label(__('Nomor PO'))
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('harga_beli')
                    ->label(__('Harga beli'))
                    ->money('IDR', locale: 'id')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('nilai_buku')
                    ->label(__('Nilai buku'))
                    ->state(fn (Asset $record): ?int => $record->bookValue())
                    ->money('IDR', locale: 'id')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                IconColumn::make('is_byod')
                    ->label(__('BYOD'))
                    ->boolean()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('category_id')
                    ->label(__('Kategori'))
                    ->relationship('category', 'nama', fn (Builder $query): Builder => $query->managedByCurrentUser()),
                SelectFilter::make('status_id')
                    ->label(__('Status'))
                    ->relationship('status', 'nama'),
                SelectFilter::make('room_id')
                    ->label(__('Lokasi'))
                    ->relationship('room', 'nama'),
                SelectFilter::make('karyawan')
                    ->label(__('Pemegang (karyawan)'))
                    ->options(fn (): array => Employee::query()->orderBy('nama')->pluck('nama', 'id')->all())
                    ->searchable()
                    ->query(fn (Builder $query, array $data): Builder => blank($data['value'] ?? null)
                        ? $query
                        : $query->where('assigned_type', (new Employee)->getMorphClass())->where('assigned_id', $data['value'])),
                TernaryFilter::make('is_byod')
                    ->label(__('BYOD')),
            ])
            ->recordActions([
                AssetTransactionActions::handOver(),
                AssetTransactionActions::takeBack(),
                EditAction::make(),
                ReplicateAction::make()
                    ->label(__('Clone'))
                    ->excludeAttributes(['kode', 'no_seri', 'status_id', 'assigned_type', 'assigned_id'])
                    ->beforeReplicaSaved(function (Asset $replica): void {
                        $replica->status_id = Status::named(Status::DISIAPKAN)->getKey();
                    })
                    ->successRedirectUrl(fn (Asset $replica): string => AssetResource::getUrl('edit', ['record' => $replica])),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    ExportBulkAction::make()
                        ->exporter(AssetExporter::class),
                    AssetTransactionActions::handOverBulk(),
                    AssetTransactionActions::takeBackBulk(),
                    BulkAction::make('markAs')
                        ->label(__('Ubah status'))
                        ->icon(Heroicon::OutlinedCheckBadge)
                        ->schema([
                            Select::make('status_id')
                                ->label(__('Status'))
                                ->options(fn (): array => Status::query()->whereIn('nama', [Status::DISIAPKAN, Status::TERSEDIA])->pluck('nama', 'id')->all())
                                ->required(),
                        ])
                        ->authorizeIndividualRecords('update')
                        ->action(function (Collection $records, array $data): void {
                            $target = Status::query()->findOrFail($data['status_id']);
                            $skipped = 0;

                            foreach ($records as $asset) {
                                try {
                                    $asset->markAs($target);
                                } catch (LogicException) {
                                    $skipped++;
                                }
                            }

                            Notification::make()
                                ->title(__(':count aset diubah ke :status.', ['count' => $records->count() - $skipped, 'status' => $target->nama]))
                                ->body($skipped ? __(':count aset dilewati karena statusnya bukan Disiapkan atau Tersedia.', ['count' => $skipped]) : null)
                                ->success()
                                ->send();
                        })
                        ->deselectRecordsAfterCompletion(),
                    BulkAction::make('moveRoom')
                        ->label(__('Pindah lokasi'))
                        ->icon(Heroicon::OutlinedMapPin)
                        ->schema([
                            Select::make('room_id')
                                ->label(__('Lokasi baru'))
                                ->options(fn (): array => Room::query()->with('floor.building')->get()
                                    ->mapWithKeys(fn (Room $room): array => [$room->id => "{$room->floor->building->nama} — {$room->floor->nama} — {$room->nama}"])
                                    ->all())
                                ->searchable()
                                ->required(),
                        ])
                        ->authorizeIndividualRecords('update')
                        ->action(fn (Collection $records, array $data) => $records->each->update(['room_id' => $data['room_id']]))
                        ->deselectRecordsAfterCompletion(),
                    BulkAction::make('changeCategory')
                        ->label(__('Ubah kategori'))
                        ->icon(Heroicon::OutlinedTag)
                        ->modalDescription(__('Kode aset tidak berubah walaupun kategorinya diganti.'))
                        ->schema([
                            Select::make('category_id')
                                ->label(__('Kategori baru'))
                                ->relationship('category', 'nama', modifyQueryUsing: fn (Builder $query): Builder => $query->managedByCurrentUser())
                                ->required(),
                        ])
                        ->authorizeIndividualRecords('update')
                        ->action(fn (Collection $records, array $data) => $records->each->update(['category_id' => $data['category_id']]))
                        ->deselectRecordsAfterCompletion(),
                ]),
            ]);
    }
}
