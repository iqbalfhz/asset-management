<?php

namespace App\Filament\Resources\Licenses\Schemas;

use App\Filament\Resources\Licenses\Tables\LicensesTable;
use App\Models\License;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class LicenseInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('Lisensi'))
                    ->columns(3)
                    ->columnSpanFull()
                    ->schema([
                        TextEntry::make('nama_software')
                            ->label(__('Nama software')),
                        TextEntry::make('role.name')
                            ->label(__('Role pengelola'))
                            ->formatStateUsing(fn (string $state): string => Str::headline($state)),
                        TextEntry::make('company.nama')
                            ->label(__('Perusahaan')),
                        TextEntry::make('seat')
                            ->label(__('Seat terpakai'))
                            ->state(fn (License $record): string => __(':terpakai dari :jumlah', ['terpakai' => $record->usedSeats(), 'jumlah' => $record->jumlah_seat])),
                        TextEntry::make('tgl_habis')
                            ->label(__('Tanggal habis'))
                            ->date('j M Y')
                            ->color(fn (License $record): ?string => LicensesTable::expiryColor($record))
                            ->placeholder(__('Tanpa batas')),
                        TextEntry::make('product_key')
                            ->label(__('Product key'))
                            // Tampil disamarkan; tombol salin tetap menyalin key lengkap.
                            ->formatStateUsing(fn (string $state): string => Str::mask($state, '•', 0, max(0, mb_strlen($state) - 5)))
                            ->copyable()
                            ->copyableState(fn (License $record): ?string => $record->product_key)
                            ->placeholder('—'),
                        TextEntry::make('harga')
                            ->label(__('Harga'))
                            ->money('IDR', locale: 'id')
                            ->placeholder('—'),
                        TextEntry::make('supplier.nama')
                            ->label(__('Supplier'))
                            ->placeholder('—'),
                        TextEntry::make('catatan')
                            ->placeholder('—')
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
