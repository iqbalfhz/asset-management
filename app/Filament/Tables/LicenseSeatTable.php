<?php

namespace App\Filament\Tables;

use App\Filament\Actions\DomainAction;
use App\Models\LicenseSeat;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Illuminate\Database\Eloquent\Builder;

// Bagian tabel seat lisensi yang dipakai di halaman lisensi, aset, karyawan, dan panel karyawan.
class LicenseSeatTable
{
    public static function software(): TextColumn
    {
        return TextColumn::make('license.nama_software')
            ->label(__('Software'))
            ->sortable();
    }

    public static function expiresAt(): TextColumn
    {
        return TextColumn::make('license.tgl_habis')
            ->label(__('Berlaku sampai'))
            ->sortable()
            ->date('j M Y')
            ->placeholder(__('Tanpa batas'))
            ->color(fn (LicenseSeat $record): ?string => $record->license->isExpired() ? 'danger' : null);
    }

    public static function installedAt(): TextColumn
    {
        return TextColumn::make('dipasang_pada')
            ->label(__('Dipasang'))
            ->sortable()
            ->dateTime('j M Y, H:i');
    }

    public static function releasedAt(): TextColumn
    {
        return TextColumn::make('dilepas_pada')
            ->label(__('Dilepas'))
            ->sortable()
            ->dateTime('j M Y, H:i')
            ->placeholder(__('Masih dipakai'));
    }

    public static function activeOnlyFilter(): Filter
    {
        return Filter::make('aktif')
            ->label(__('Hanya yang masih dipakai'))
            ->toggle()
            ->default()
            ->query(fn (Builder $query): Builder => $query->whereNull('dilepas_pada'));
    }

    public static function releaseAction(): Action
    {
        return Action::make('release')
            ->label(__('Lepas'))
            ->icon(Heroicon::OutlinedArrowUturnLeft)
            ->color('gray')
            ->visible(fn (LicenseSeat $record): bool => $record->dilepas_pada === null && (auth()->user()?->can('update', $record->license) ?? false))
            ->requiresConfirmation()
            ->modalDescription(fn (LicenseSeat $record): string => __('Seat milik :pemakai dilepas dan bisa dipakai lagi. Hapus juga instalasi atau akunnya di sisi software.', ['pemakai' => $record->holderLabel()]))
            ->action(fn (LicenseSeat $record, Action $action) => DomainAction::run($action, fn () => $record->release(), __('Seat dilepas')));
    }
}
