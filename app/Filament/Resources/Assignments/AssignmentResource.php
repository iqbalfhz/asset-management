<?php

namespace App\Filament\Resources\Assignments;

use App\Enums\AssignmentStatus;
use App\Enums\AssignmentType;
use App\Filament\Resources\Assignments\Pages\ListAssignments;
use App\Models\Assignment;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Component;

// Daftar transaksi saja; serah-terima dan pengembalian dibuat dari halaman aset.
class AssignmentResource extends Resource
{
    protected static ?string $model = Assignment::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowsRightLeft;

    protected static ?int $navigationSort = 2;

    public static function getModelLabel(): string
    {
        return __('Serah-terima');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Serah-terima');
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['asset', 'createdBy']))
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('tanggal')
                    ->date('j M Y')
                    ->sortable(),
                TextColumn::make('asset.kode')
                    ->label(__('Aset'))
                    ->description(fn (Assignment $record): string => $record->asset->nama)
                    ->searchable()
                    ->hidden(fn (Component $livewire): bool => $livewire instanceof RelationManager),
                TextColumn::make('jenis')
                    ->badge(),
                TextColumn::make('pemegang')
                    ->label(__('Pemegang'))
                    ->state(fn (Assignment $record): string => $record->targetLabel()),
                TextColumn::make('kondisi'),
                TextColumn::make('no_bast')
                    ->label(__('Nomor BAST'))
                    ->searchable(),
                TextColumn::make('expected_return_at')
                    ->label(__('Tanggal kembali'))
                    ->date('j M Y')
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('status')
                    ->badge(),
                TextColumn::make('accepted_at')
                    ->label(__('Diterima pada'))
                    ->dateTime('j M Y H:i')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('createdBy.name')
                    ->label(__('Dicatat oleh'))
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(AssignmentStatus::class),
                SelectFilter::make('jenis')
                    ->options(AssignmentType::class),
            ])
            ->recordActions([
                Action::make('bast')
                    ->label(__('BAST'))
                    ->icon(Heroicon::OutlinedDocumentArrowDown)
                    ->url(fn (Assignment $record): string => route('filament.admin.bast.download', $record))
                    ->openUrlInNewTab(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAssignments::route('/'),
        ];
    }
}
