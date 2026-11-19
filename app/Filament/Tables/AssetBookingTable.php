<?php

namespace App\Filament\Tables;

use App\Enums\BookingStatus;
use App\Filament\Actions\DomainAction;
use App\Models\AssetBooking;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;

// Bagian tabel pesanan barang bersama yang dipakai di menu Peminjaman admin dan Pinjam barang karyawan.
class AssetBookingTable
{
    public static function item(): TextColumn
    {
        return TextColumn::make('asset.kode')
            ->label(__('Barang'))
            ->sortable()
            ->description(fn (AssetBooking $record): string => $record->asset->nama);
    }

    public static function period(): TextColumn
    {
        return TextColumn::make('mulai')
            ->label(__('Waktu'))
            ->formatStateUsing(fn (AssetBooking $record): string => $record->period())
            ->sortable();
    }

    public static function purpose(): TextColumn
    {
        return TextColumn::make('keperluan')
            ->label(__('Keperluan'))
            ->limit(80)
            ->wrap();
    }

    public static function status(): TextColumn
    {
        return TextColumn::make('status')
            ->label(__('Status'))
            ->state(fn (AssetBooking $record): BookingStatus => $record->status())
            ->badge();
    }

    public static function cancelReason(): TextColumn
    {
        return TextColumn::make('alasan_batal')
            ->label(__('Alasan batal'))
            ->placeholder('—');
    }

    public static function cancelAction(): Action
    {
        return Action::make('cancel')
            ->label(__('Batalkan'))
            ->icon(Heroicon::OutlinedXMark)
            ->color('danger')
            ->visible(fn (AssetBooking $record): bool => $record->isCancellable())
            ->action(fn (AssetBooking $record, array $data, Action $action) => DomainAction::run($action, fn () => $record->cancel($data['alasan_batal'] ?? null), __('Pesanan dibatalkan')));
    }
}
