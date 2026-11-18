<?php

namespace App\Filament\Resources\Accessories\RelationManagers;

use App\Filament\Actions\DomainAction;
use App\Filament\Schemas\Components\EmployeeSelect;
use App\Filament\Support\AllocatableStock;
use App\Models\Accessory;
use App\Models\AccessoryCheckout;
use App\Models\Employee;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class CheckoutsRelationManager extends RelationManager
{
    protected static string $relationship = 'allocations';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('Pemegang');
    }

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        /** @var Accessory $accessory */
        $accessory = $this->getOwnerRecord();

        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['accessory', 'employee', 'giver']))
            ->defaultSort('diserahkan_pada', 'desc')
            ->columns([
                TextColumn::make('employee.nama')
                    ->label(__('Karyawan'))
                    ->description(fn (AccessoryCheckout $record): string => $record->employee->nik),
                TextColumn::make('jumlah'),
                TextColumn::make('diserahkan_pada')
                    ->label(__('Diserahkan'))
                    ->dateTime('j M Y, H:i')
                    ->sortable(),
                TextColumn::make('giver.name')
                    ->label(__('Oleh'))
                    ->placeholder('—')
                    ->toggleable(),
                AllocatableStock::releasedAt(AccessoryCheckout::class, __('Dikembalikan')),
                TextColumn::make('catatan')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                AllocatableStock::openOnlyFilter(AccessoryCheckout::class),
            ])
            ->headerActions([
                Action::make('checkOut')
                    ->label(__('Serahkan'))
                    ->icon(Heroicon::OutlinedArrowUpRight)
                    ->visible(fn (): bool => auth()->user()?->can('update', $accessory) ?? false)
                    ->schema([
                        EmployeeSelect::make(__('Karyawan'), $accessory->company_id),
                        TextInput::make('jumlah')
                            ->integer()
                            ->minValue(1)
                            ->default(1)
                            ->required(),
                        Textarea::make('catatan')
                            ->maxLength(1000),
                    ])
                    // Karyawan dicari ulang dengan hak lihat user, bukan dipercaya dari isian form.
                    ->action(fn (array $data, Action $action) => DomainAction::run(
                        $action,
                        fn () => $accessory->checkOut(Employee::query()->findOrFail($data['employee_id']), (int) $data['jumlah'], $data['catatan'] ?? null),
                        __('Aksesori diserahkan'),
                    )),
            ])
            ->recordActions([
                AllocatableStock::releaseAction(__('Kembalikan'), __('Aksesori dikembalikan'), 'accessory'),
            ]);
    }
}
