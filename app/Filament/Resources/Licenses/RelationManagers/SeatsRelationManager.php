<?php

namespace App\Filament\Resources\Licenses\RelationManagers;

use App\Filament\Actions\DomainAction;
use App\Filament\Schemas\Components\AssetSelect;
use App\Filament\Schemas\Components\EmployeeSelect;
use App\Filament\Tables\LicenseSeatTable;
use App\Models\Asset;
use App\Models\Employee;
use App\Models\License;
use App\Models\LicenseSeat;
use Filament\Actions\Action;
use Filament\Forms\Components\ToggleButtons;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

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
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['license', 'employee', 'asset', 'installer']))
            ->defaultSort('dipasang_pada', 'desc')
            ->columns([
                TextColumn::make('pemegang')
                    ->label(__('Pemakai'))
                    ->state(fn (LicenseSeat $record): string => $record->holderLabel())
                    ->description(fn (LicenseSeat $record): string => $record->employee_id ? __('Karyawan') : __('Aset')),
                LicenseSeatTable::installedAt(),
                TextColumn::make('installer.name')
                    ->label(__('Oleh'))
                    ->sortable()
                    ->placeholder('—')
                    ->toggleable(),
                LicenseSeatTable::releasedAt(),
            ])
            ->filters([
                LicenseSeatTable::activeOnlyFilter(),
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
                        EmployeeSelect::make(__('Karyawan'), $license->company_id)
                            ->visible(fn (Get $get): bool => $get('jenis') === 'karyawan'),
                        AssetSelect::make(__('Aset'), $license->company_id)
                            ->visible(fn (Get $get): bool => $get('jenis') === 'aset'),
                    ])
                    ->action(function (array $data, Action $action) use ($license): void {
                        // Pemakai dicari ulang dengan hak lihat user, bukan dipercaya dari isian form.
                        $holder = $data['jenis'] === 'karyawan'
                            ? Employee::query()->findOrFail($data['employee_id'])
                            : Asset::query()->with('status')->findOrFail($data['asset_id']);

                        DomainAction::run($action, fn () => $license->assignSeat($holder), __('Seat dipasang'));
                    }),
            ])
            ->recordActions([
                LicenseSeatTable::releaseAction(),
            ]);
    }
}
