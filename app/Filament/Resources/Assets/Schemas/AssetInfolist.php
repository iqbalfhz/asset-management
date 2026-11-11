<?php

namespace App\Filament\Resources\Assets\Schemas;

use App\Models\Asset;
use App\Models\Assignment;
use App\Models\Room;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\KeyValueEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Colors\Color;

// Tampilan hanya-baca, juga tujuan scan QR dari HP.
class AssetInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('Identitas'))
                    ->columns(2)
                    ->columnSpanFull()
                    ->schema([
                        TextEntry::make('kode')
                            ->copyable(),
                        TextEntry::make('nama'),
                        TextEntry::make('category.nama')
                            ->label(__('Kategori'))
                            ->badge()
                            ->color(fn (Asset $record): ?array => $record->category->warna ? Color::hex($record->category->warna) : null),
                        TextEntry::make('status.nama')
                            ->label(__('Status'))
                            ->badge()
                            ->color(fn (Asset $record): ?array => $record->status->warna ? Color::hex($record->status->warna) : null),
                        TextEntry::make('model')
                            ->label(__('Model'))
                            ->state(fn (Asset $record): ?string => $record->assetModel ? "{$record->assetModel->manufacturer->nama} {$record->assetModel->nama}" : null)
                            ->placeholder('—'),
                        TextEntry::make('no_seri')
                            ->label(__('Nomor seri'))
                            ->copyable()
                            ->placeholder('—'),
                        IconEntry::make('is_byod')
                            ->label(__('Perangkat pribadi (BYOD)'))
                            ->boolean(),
                    ]),
                Section::make(__('Pemegang dan lokasi'))
                    ->columns(2)
                    ->columnSpanFull()
                    ->schema([
                        TextEntry::make('pemegang')
                            ->label(__('Pemegang'))
                            ->state(fn (Asset $record): ?string => $record->assigned ? Assignment::labelFor($record->assigned) : null)
                            ->placeholder('—'),
                        TextEntry::make('lokasi')
                            ->label(__('Lokasi sekarang'))
                            ->state(fn (Asset $record): ?string => static::roomLabel($record->room))
                            ->placeholder('—'),
                        TextEntry::make('lokasi_default')
                            ->label(__('Lokasi default'))
                            ->state(fn (Asset $record): ?string => static::roomLabel($record->defaultRoom))
                            ->placeholder('—'),
                        TextEntry::make('next_audit_date')
                            ->label(__('Audit berikutnya'))
                            ->date('j M Y')
                            ->placeholder(__('Belum dijadwalkan')),
                    ]),
                Section::make(__('Pembelian'))
                    ->columns(2)
                    ->columnSpanFull()
                    ->collapsible()
                    ->schema([
                        TextEntry::make('supplier.nama')
                            ->label(__('Supplier'))
                            ->placeholder('—'),
                        TextEntry::make('order_number')
                            ->label(__('Nomor PO'))
                            ->placeholder('—'),
                        TextEntry::make('tgl_beli')
                            ->label(__('Tanggal beli'))
                            ->date('j M Y')
                            ->placeholder('—'),
                        TextEntry::make('harga_beli')
                            ->label(__('Harga beli'))
                            ->money('IDR', locale: 'id')
                            ->placeholder('—'),
                        TextEntry::make('depreciation.nama')
                            ->label(__('Kelas penyusutan'))
                            ->placeholder('—'),
                        TextEntry::make('nilai_buku')
                            ->label(__('Nilai buku'))
                            ->state(fn (Asset $record): ?int => $record->bookValue())
                            ->money('IDR', locale: 'id')
                            ->placeholder('—'),
                        TextEntry::make('garansi_sampai')
                            ->label(__('Garansi sampai'))
                            ->date('j M Y')
                            ->placeholder('—'),
                        TextEntry::make('eol_date')
                            ->label(__('Tanggal EOL'))
                            ->date('j M Y')
                            ->placeholder('—'),
                    ]),
                Section::make(__('Spesifikasi dan catatan'))
                    ->columnSpanFull()
                    ->collapsible()
                    ->schema([
                        KeyValueEntry::make('spesifikasi')
                            ->hiddenLabel()
                            ->keyLabel(__('Kunci'))
                            ->valueLabel(__('Nilai'))
                            ->placeholder('—'),
                        TextEntry::make('notes')
                            ->label(__('Catatan'))
                            ->placeholder('—'),
                    ]),
            ]);
    }

    private static function roomLabel(?Room $room): ?string
    {
        return $room ? "{$room->floor->building->nama} — {$room->floor->nama} — {$room->nama}" : null;
    }
}
