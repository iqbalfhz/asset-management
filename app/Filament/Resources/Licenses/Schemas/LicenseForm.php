<?php

namespace App\Filament\Resources\Licenses\Schemas;

use App\Filament\Schemas\Components\CompanySelect;
use App\Filament\Schemas\Components\ManagingRoleSelect;
use App\Models\License;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class LicenseForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                CompanySelect::make(),
                TextInput::make('nama_software')
                    ->label(__('Nama software'))
                    ->required()
                    ->maxLength(255),
                ManagingRoleSelect::make(),
                TextInput::make('product_key')
                    ->label(__('Product key'))
                    ->password()
                    ->revealable()
                    ->autocomplete('off')
                    ->maxLength(1000),
                TextInput::make('jumlah_seat')
                    ->label(__('Jumlah seat'))
                    ->integer()
                    ->required()
                    ->minValue(fn (?License $record): int => max(1, $record?->usedSeats() ?? 0))
                    ->helperText(fn (?License $record): ?string => $record ? __(':count seat sedang dipakai.', ['count' => $record->usedSeats()]) : null),
                DatePicker::make('tgl_habis')
                    ->label(__('Tanggal habis'))
                    ->helperText(__('Kosongkan untuk lisensi tanpa masa berlaku.')),
                TextInput::make('harga')
                    ->label(__('Harga'))
                    ->integer()
                    ->minValue(0)
                    ->prefix('Rp'),
                Select::make('supplier_id')
                    ->label(__('Supplier'))
                    ->relationship('supplier', 'nama')
                    ->searchable()
                    ->preload(),
                Textarea::make('catatan')
                    ->maxLength(2000)
                    ->columnSpanFull(),
            ]);
    }
}
