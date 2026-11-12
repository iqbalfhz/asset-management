<?php

namespace App\Filament\Resources\Licenses\RelationManagers;

use App\Models\Asset;
use App\Models\Employee;
use App\Models\License;
use App\Models\LicenseSeat;
use App\Models\Status;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\ToggleButtons;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use LogicException;

class SeatsRelationManager extends RelationManager
{
    protected static string $relationship = 'seats';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('Seat');
    }

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        /** @var License $license */
        $license = $this->getOwnerRecord();
        $canUpdate = fn (): bool => auth()->user()?->can('update', $license) ?? false;

        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['employee', 'asset', 'installer']))
            ->defaultSort('dipasang_pada', 'desc')
            ->columns([
                TextColumn::make('pemegang')
                    ->label(__('Pemakai'))
                    ->state(fn (LicenseSeat $record): string => $record->holderLabel())
                    ->description(fn (LicenseSeat $record): string => $record->employee_id ? __('Karyawan') : __('Aset')),
                TextColumn::make('dipasang_pada')
                    ->label(__('Dipasang'))
                    ->dateTime('j M Y, H:i'),
                TextColumn::make('installer.name')
                    ->label(__('Oleh'))
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('dilepas_pada')
                    ->label(__('Dilepas'))
                    ->dateTime('j M Y, H:i')
                    ->placeholder(__('Masih dipakai')),
            ])
            ->filters([
                Filter::make('aktif')
                    ->label(__('Hanya yang masih dipakai'))
                    ->toggle()
                    ->default()
                    ->query(fn (Builder $query): Builder => $query->whereNull('dilepas_pada')),
            ])
            ->headerActions([
                Action::make('assign')
                    ->label(__('Pasang seat'))
                    ->icon(Heroicon::OutlinedPlus)
                    ->visible($canUpdate)
                    ->schema([
                        ToggleButtons::make('jenis')
                            ->label(__('Dipasang ke'))
                            ->options(['karyawan' => __('Karyawan'), 'aset' => __('Aset')])
                            ->default('karyawan')
                            ->inline()
                            ->required()
                            ->live(),
                        Select::make('employee_id')
                            ->label(__('Karyawan'))
                            ->searchable()
                            ->getSearchResultsUsing(fn (string $search): array => Employee::query()
                                ->where('company_id', $license->company_id)
                                ->where('is_active', true)
                                ->where(fn (Builder $query) => $query->where('nama', 'like', "%{$search}%")->orWhere('nik', 'like', "%{$search}%"))
                                ->orderBy('nama')
                                ->limit(50)
                                ->pluck('nama', 'id')
                                ->all())
                            ->getOptionLabelUsing(fn (int|string $value): ?string => Employee::query()->whereKey($value)->value('nama'))
                            ->visible(fn (Get $get): bool => $get('jenis') === 'karyawan')
                            ->required(),
                        Select::make('asset_id')
                            ->label(__('Aset'))
                            ->searchable()
                            ->getSearchResultsUsing(fn (string $search): array => Asset::query()
                                ->where('company_id', $license->company_id)
                                ->whereDoesntHave('status', fn (Builder $query) => $query->where('nama', Status::DIHAPUSKAN))
                                ->where(fn (Builder $query) => $query->where('kode', 'like', "%{$search}%")->orWhere('nama', 'like', "%{$search}%"))
                                ->orderBy('kode')
                                ->limit(50)
                                ->get()
                                ->mapWithKeys(fn (Asset $asset): array => [$asset->id => "{$asset->kode} — {$asset->nama}"])
                                ->all())
                            ->getOptionLabelUsing(fn (int|string $value): ?string => Asset::query()->whereKey($value)->value('kode'))
                            ->visible(fn (Get $get): bool => $get('jenis') === 'aset')
                            ->required(),
                    ])
                    ->action(function (array $data, Action $action) use ($license): void {
                        // Pemakai dicari ulang dengan hak lihat user, bukan dipercaya dari isian form.
                        $holder = $data['jenis'] === 'karyawan'
                            ? Employee::query()->findOrFail($data['employee_id'])
                            : Asset::query()->with('status')->findOrFail($data['asset_id']);

                        static::run($action, fn () => $license->assignSeat($holder), __('Seat dipasang'));
                    }),
            ])
            ->recordActions([
                Action::make('release')
                    ->label(__('Lepas'))
                    ->icon(Heroicon::OutlinedArrowUturnLeft)
                    ->color('gray')
                    ->visible(fn (LicenseSeat $record): bool => $record->dilepas_pada === null && $canUpdate())
                    ->requiresConfirmation()
                    ->modalDescription(fn (LicenseSeat $record): string => __('Seat milik :pemakai dilepas dan bisa dipakai lagi. Hapus juga instalasi atau akunnya di sisi software.', ['pemakai' => $record->holderLabel()]))
                    ->action(fn (LicenseSeat $record, Action $action) => static::run($action, fn () => $record->release(), __('Seat dilepas'))),
            ]);
    }

    public static function run(Action $action, callable $transaction, string $successTitle): void
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
