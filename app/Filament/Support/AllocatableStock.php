<?php

namespace App\Filament\Support;

use App\Filament\Actions\DomainAction;
use App\Filament\Schemas\Components\CompanySelect;
use App\Filament\Schemas\Components\ManagingRoleSelect;
use App\Filament\Tables\Columns\CompanyColumn;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\Column;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Unique;

// Bagian layar bersama untuk barang berjumlah yang keluar lalu kembali (aksesori dan komponen), lihat AllocatesStock.
class AllocatableStock
{
    /**
     * @return array<int, Field>
     */
    public static function formFields(): array
    {
        return [
            CompanySelect::make(),
            TextInput::make('nama')
                ->required()
                ->maxLength(255)
                ->unique(ignoreRecord: true, modifyRuleUsing: fn (Unique $rule, Get $get): Unique => $rule->where('company_id', $get('company_id'))),
            ManagingRoleSelect::make(),
            Select::make('manufacturer_id')
                ->label(__('Produsen'))
                ->relationship('manufacturer', 'nama')
                ->searchable()
                ->preload(),
            TextInput::make('jumlah')
                ->label(__('Jumlah dimiliki'))
                ->helperText(fn (?Model $record): ?string => $record ? __(':count sedang dipakai.', ['count' => $record->allocatedQuantity()]) : null)
                ->integer()
                ->minValue(fn (?Model $record): int => $record?->allocatedQuantity() ?? 0)
                ->default(0)
                ->required(),
            TextInput::make('stok_minimum')
                ->label(__('Batas minimum'))
                ->helperText(__('Angka Tersedia jadi merah kalau sama dengan atau di bawah angka ini. Isi 0 kalau tidak perlu dipantau.'))
                ->integer()
                ->minValue(0)
                ->default(0)
                ->required(),
            Textarea::make('catatan')
                ->maxLength(2000)
                ->columnSpanFull(),
        ];
    }

    // Daftar barang di halaman resource-nya.
    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['role', 'manufacturer', 'company']))
            ->defaultSort('nama')
            ->columns(static::tableColumns())
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ]);
    }

    public static function infolist(Schema $schema, string $title): Schema
    {
        return $schema->components([
            Section::make($title)
                ->columns(3)
                ->columnSpanFull()
                ->schema(static::infolistEntries()),
        ]);
    }

    /**
     * @return array<int, Column>
     */
    private static function tableColumns(): array
    {
        return [
            TextColumn::make('nama')
                ->description(fn (Model $record): string => Str::headline($record->role->name))
                ->searchable()
                ->sortable(),
            TextColumn::make('tersedia')
                ->label(__('Tersedia'))
                ->state(fn (Model $record): string => __(':tersedia dari :jumlah', ['tersedia' => $record->availableQuantity(), 'jumlah' => $record->jumlah]))
                ->color(fn (Model $record): ?string => $record->isLow() ? 'danger' : null),
            TextColumn::make('stok_minimum')
                ->label(__('Minimum'))
                ->formatStateUsing(fn (int $state): string => $state > 0 ? (string) $state : '—')
                ->sortable(),
            TextColumn::make('manufacturer.nama')
                ->label(__('Produsen'))
                ->placeholder('—')
                ->sortable()
                ->toggleable(),
            CompanyColumn::make(),
        ];
    }

    /**
     * @return array<int, TextEntry>
     */
    private static function infolistEntries(): array
    {
        return [
            TextEntry::make('nama'),
            TextEntry::make('role.name')
                ->label(__('Role pengelola'))
                ->formatStateUsing(fn (string $state): string => Str::headline($state)),
            TextEntry::make('company.nama')
                ->label(__('Perusahaan')),
            TextEntry::make('tersedia')
                ->label(__('Tersedia'))
                ->state(fn (Model $record): string => __(':tersedia dari :jumlah', ['tersedia' => $record->availableQuantity(), 'jumlah' => $record->jumlah]))
                ->color(fn (Model $record): ?string => $record->isLow() ? 'danger' : null),
            TextEntry::make('stok_minimum')
                ->label(__('Batas minimum'))
                ->formatStateUsing(fn (int $state): string => $state > 0 ? (string) $state : __('Tidak dipantau')),
            TextEntry::make('manufacturer.nama')
                ->label(__('Produsen'))
                ->placeholder('—'),
            TextEntry::make('catatan')
                ->placeholder('—'),
        ];
    }

    /**
     * Baris yang masih keluar saja, bisa dimatikan untuk melihat riwayat.
     *
     * @param  class-string<Model>  $line
     */
    public static function openOnlyFilter(string $line): Filter
    {
        return Filter::make('aktif')
            ->label(__('Hanya yang masih dipakai'))
            ->toggle()
            ->default()
            ->query(fn (Builder $query): Builder => $query->whereNull($line::RELEASED_AT));
    }

    /**
     * @param  class-string<Model>  $line
     */
    public static function openedAt(string $line, string $label): TextColumn
    {
        return TextColumn::make($line::OPENED_AT)
            ->label($label)
            ->dateTime('j M Y, H:i')
            ->sortable();
    }

    /**
     * @param  class-string<Model>  $line
     */
    public static function releasedAt(string $line, string $label): TextColumn
    {
        return TextColumn::make($line::RELEASED_AT)
            ->label($label)
            ->dateTime('j M Y, H:i')
            ->placeholder(__('Masih dipakai'))
            ->sortable();
    }

    // Kembalikan atau lepas satu baris; hanya untuk yang boleh mengubah barangnya.
    public static function releaseAction(string $label, string $successTitle, string $itemRelation): Action
    {
        return Action::make('release')
            ->label($label)
            ->icon(Heroicon::OutlinedArrowUturnLeft)
            ->color('gray')
            ->visible(fn (Model $record): bool => $record->isOpen() && (auth()->user()?->can('update', $record->{$itemRelation}) ?? false))
            ->requiresConfirmation()
            ->action(fn (Model $record, Action $action) => DomainAction::run($action, fn () => $record->release(), $successTitle));
    }
}
