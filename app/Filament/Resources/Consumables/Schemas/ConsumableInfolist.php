<?php

namespace App\Filament\Resources\Consumables\Schemas;

use App\Models\Consumable;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class ConsumableInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('Barang'))
                    ->columns(3)
                    ->columnSpanFull()
                    ->schema([
                        TextEntry::make('nama'),
                        TextEntry::make('role.name')
                            ->label(__('Role pengelola'))
                            ->formatStateUsing(fn (string $state): string => Str::headline($state)),
                        TextEntry::make('company.nama')
                            ->label(__('Perusahaan')),
                        TextEntry::make('stok')
                            ->formatStateUsing(fn (int $state, Consumable $record): string => "{$state} {$record->satuan}")
                            ->color(fn (Consumable $record): ?string => $record->isLow() ? 'danger' : null),
                        TextEntry::make('stok_minimum')
                            ->label(__('Batas minimum'))
                            ->formatStateUsing(fn (int $state, Consumable $record): string => $state > 0 ? "{$state} {$record->satuan}" : __('Tidak dipantau')),
                        TextEntry::make('catatan')
                            ->placeholder('—'),
                    ]),
            ]);
    }
}
