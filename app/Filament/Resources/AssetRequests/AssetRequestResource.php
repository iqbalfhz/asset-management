<?php

namespace App\Filament\Resources\AssetRequests;

use App\Enums\AssetRequestStatus;
use App\Filament\Actions\DomainAction;
use App\Filament\Resources\AssetRequests\Pages\ListAssetRequests;
use App\Filament\Resources\Assets\Actions\AssetTransactionActions;
use App\Filament\Support\BadgeColor;
use App\Models\Asset;
use App\Models\AssetRequest;
use App\Models\Assignment;
use App\Models\Status;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

// Permintaan dibuat karyawan dari panelnya; admin memutuskan lalu menyerahkan asetnya.
class AssetRequestResource extends Resource
{
    protected static ?string $model = AssetRequest::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedHandRaised;

    protected static ?int $navigationSort = 1;

    public static function getModelLabel(): string
    {
        return __('Permintaan aset');
    }

    public static function getPluralModelLabel(): string
    {
        return trans_choice('Permintaan aset|Permintaan aset', 2);
    }

    public static function getNavigationGroup(): ?string
    {
        return __('Transaksi');
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getNavigationBadge(): ?string
    {
        $count = AssetRequest::query()->where('status', AssetRequestStatus::Diajukan)->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeTooltip(): string
    {
        return __('Permintaan yang belum diputuskan');
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['employee', 'category', 'assignment.asset']))
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('created_at')
                    ->label(__('Diajukan'))
                    ->sortable()
                    ->date('j M Y'),
                TextColumn::make('employee.nama')
                    ->label(__('Karyawan'))
                    ->sortable()
                    ->description(fn (AssetRequest $record): string => $record->employee->nik)
                    ->searchable(),
                TextColumn::make('category.nama')
                    ->label(__('Kategori'))
                    ->sortable()
                    ->badge()
                    ->color(fn (AssetRequest $record): ?array => BadgeColor::fromHex($record->category->warna)),
                TextColumn::make('alasan')
                    ->label(__('Alasan'))
                    ->limit(60)
                    ->wrap(),
                TextColumn::make('status')
                    ->label(__('Status'))
                    ->sortable()
                    ->badge(),
                TextColumn::make('assignment.asset.kode')
                    ->label(__('Aset diserahkan'))
                    ->sortable()
                    ->placeholder('—'),
                TextColumn::make('catatan')
                    ->label(__('Catatan'))
                    ->limit(60)
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label(__('Status'))
                    ->options(AssetRequestStatus::class),
            ])
            ->recordActions([
                DomainAction::transition('approve', __('Setujui'), Heroicon::OutlinedCheck, AssetRequestStatus::Diajukan)
                    ->schema([
                        Textarea::make('catatan')
                            ->label(__('Catatan (opsional)'))
                            ->helperText(__('Terlihat oleh karyawan yang meminta.'))
                            ->maxLength(2000),
                    ])
                    ->action(fn (AssetRequest $record, array $data, Action $action) => DomainAction::run($action, fn () => $record->approve($data['catatan'] ?? null), __('Permintaan disetujui'))),
                DomainAction::transition('reject', __('Tolak'), Heroicon::OutlinedXMark, AssetRequestStatus::Diajukan)
                    ->color('gray')
                    ->schema([
                        Textarea::make('catatan')
                            ->label(__('Alasan ditolak'))
                            ->helperText(__('Terlihat oleh karyawan yang meminta.'))
                            ->required()
                            ->maxLength(2000),
                    ])
                    ->action(fn (AssetRequest $record, array $data, Action $action) => DomainAction::run($action, fn () => $record->reject($data['catatan']), __('Permintaan ditolak'))),
                static::handOverAction(),
            ]);
    }

    private static function handOverAction(): Action
    {
        return DomainAction::transition('handOver', __('Serahkan'), Heroicon::OutlinedArrowUpRight, AssetRequestStatus::Disetujui)
            ->hidden(fn (AssetRequest $record): bool => $record->isWaitingForAcceptance())
            ->modalHeading(__('Serah-terima aset'))
            ->modalDescription(fn (AssetRequest $record): string => __('Aset diserahkan ke :nama. Permintaan selesai begitu penerimaannya tercatat.', ['nama' => $record->employee->nama]))
            ->schema([
                Select::make('asset_id')
                    ->label(__('Aset'))
                    ->helperText(fn (AssetRequest $record): string => __('Aset Tersedia dari kategori :kategori.', ['kategori' => $record->category->nama]))
                    ->options(fn (AssetRequest $record): array => Asset::query()
                        ->where('category_id', $record->category_id)
                        ->where('company_id', $record->employee->company_id)
                        ->whereRelation('status', 'nama', Status::TERSEDIA)
                        ->orderBy('kode')
                        ->get()
                        ->mapWithKeys(fn (Asset $asset): array => [$asset->getKey() => Assignment::labelFor($asset)])
                        ->all())
                    ->searchable()
                    ->required(),
                ...AssetTransactionActions::handOverDetailFields(),
            ])
            ->action(function (AssetRequest $record, array $data, Action $action): void {
                $noBast = DomainAction::attempt(
                    fn (): string => $record->handOver(Asset::query()->findOrFail($data['asset_id']), $data),
                    __('Serah-terima dibatalkan'),
                    fn () => $action->halt(),
                );

                AssetTransactionActions::notifySaved(__('Serah-terima tersimpan'), $noBast);
            });
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAssetRequests::route('/'),
        ];
    }
}
