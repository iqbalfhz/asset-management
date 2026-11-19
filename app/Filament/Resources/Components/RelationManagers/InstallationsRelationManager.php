<?php

namespace App\Filament\Resources\Components\RelationManagers;

use App\Filament\Actions\DomainAction;
use App\Filament\Schemas\Components\AssetSelect;
use App\Filament\Support\AllocatableStock;
use App\Models\Asset;
use App\Models\Assignment;
use App\Models\Component;
use App\Models\ComponentInstallation;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class InstallationsRelationManager extends RelationManager
{
    protected static string $relationship = 'allocations';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('Terpasang di');
    }

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        /** @var Component $component */
        $component = $this->getOwnerRecord();

        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['component', 'asset', 'installer']))
            ->defaultSort(ComponentInstallation::OPENED_AT, 'desc')
            ->columns([
                TextColumn::make('aset')
                    ->label(__('Aset'))
                    ->state(fn (ComponentInstallation $record): string => Assignment::labelFor($record->asset)),
                TextColumn::make('jumlah')
                    ->label(__('Jumlah'))
                    ->sortable(),
                AllocatableStock::openedAt(ComponentInstallation::class, __('Dipasang')),
                TextColumn::make('installer.name')
                    ->label(__('Oleh'))
                    ->sortable()
                    ->placeholder('—')
                    ->toggleable(),
                AllocatableStock::releasedAt(ComponentInstallation::class, __('Dilepas')),
                TextColumn::make('catatan')
                    ->label(__('Catatan'))
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                AllocatableStock::openOnlyFilter(ComponentInstallation::class),
            ])
            ->headerActions([
                Action::make('install')
                    ->label(__('Pasang'))
                    ->icon(Heroicon::OutlinedPlus)
                    ->visible(fn (): bool => auth()->user()?->can('update', $component) ?? false)
                    ->schema([
                        AssetSelect::make(__('Aset'), $component->company_id),
                        TextInput::make('jumlah')
                            ->label(__('Jumlah'))
                            ->integer()
                            ->minValue(1)
                            ->default(1)
                            ->required(),
                        Textarea::make('catatan')
                            ->label(__('Catatan'))
                            ->maxLength(1000),
                    ])
                    // Aset dicari ulang dengan hak lihat user, bukan dipercaya dari isian form.
                    ->action(fn (array $data, Action $action) => DomainAction::run(
                        $action,
                        fn () => $component->install(Asset::query()->with('status')->findOrFail($data['asset_id']), (int) $data['jumlah'], $data['catatan'] ?? null),
                        __('Komponen dipasang'),
                    )),
            ])
            ->recordActions([
                AllocatableStock::releaseAction(__('Lepas'), __('Komponen dilepas'), 'component'),
            ]);
    }
}
