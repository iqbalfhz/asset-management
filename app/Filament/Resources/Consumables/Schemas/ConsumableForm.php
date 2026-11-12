<?php

namespace App\Filament\Resources\Consumables\Schemas;

use App\Filament\Schemas\Components\CompanySelect;
use App\Filament\Schemas\Components\ManagingRoleSelect;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Validation\Rules\Unique;

class ConsumableForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                CompanySelect::make(),
                TextInput::make('nama')
                    ->required()
                    ->maxLength(255)
                    ->unique(ignoreRecord: true, modifyRuleUsing: fn (Unique $rule, Get $get): Unique => $rule->where('company_id', $get('company_id'))),
                TextInput::make('satuan')
                    ->required()
                    ->maxLength(30)
                    ->datalist(['pcs', 'rim', 'kotak', 'botol', 'roll', 'pak']),
                ManagingRoleSelect::make(),
                TextInput::make('stok_minimum')
                    ->label(__('Batas minimum'))
                    ->helperText(__('Muncul di dashboard kalau stok sama dengan atau di bawah angka ini. Isi 0 kalau tidak perlu dipantau.'))
                    ->integer()
                    ->minValue(0)
                    ->default(0)
                    ->required(),
                TextInput::make('stok_awal')
                    ->label(__('Stok awal'))
                    ->helperText(__('Dicatat sebagai transaksi masuk. Setelah ini, stok hanya berubah lewat Masuk dan Keluar.'))
                    ->integer()
                    ->minValue(0)
                    ->default(0)
                    ->visibleOn('create'),
                Textarea::make('catatan')
                    ->maxLength(2000)
                    ->columnSpanFull(),
            ]);
    }
}
