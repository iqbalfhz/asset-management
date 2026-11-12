<?php

namespace App\Filament\Resources\Licenses\Tables;

use App\Models\License;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class LicensesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['role', 'supplier'])->withCount('activeSeats'))
            ->defaultSort('nama_software')
            ->columns([
                TextColumn::make('nama_software')
                    ->label(__('Software'))
                    ->description(fn (License $record): string => Str::headline($record->role->name))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('active_seats_count')
                    ->label(__('Seat terpakai'))
                    ->formatStateUsing(fn (int $state, License $record): string => "{$state} / {$record->jumlah_seat}")
                    ->color(fn (int $state, License $record): ?string => $state >= $record->jumlah_seat ? 'warning' : null),
                TextColumn::make('tgl_habis')
                    ->label(__('Tanggal habis'))
                    ->date('j M Y')
                    ->color(fn (License $record): ?string => static::expiryColor($record))
                    ->placeholder(__('Tanpa batas'))
                    ->sortable(),
                TextColumn::make('supplier.nama')
                    ->label(__('Supplier'))
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('harga')
                    ->label(__('Harga'))
                    ->money('IDR', locale: 'id')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Filter::make('habis_segera')
                    ->label(__('Habis dalam :hari hari atau sudah lewat', ['hari' => License::EXPIRY_WARNING_DAYS]))
                    ->toggle()
                    ->query(fn (Builder $query): Builder => $query->expiringSoon()),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ]);
    }

    // Merah kalau sudah lewat, kuning kalau habis dalam masa peringatan.
    public static function expiryColor(License $license): ?string
    {
        return match (true) {
            $license->tgl_habis === null => null,
            $license->isExpired() => 'danger',
            $license->tgl_habis->lte(today()->addDays(License::EXPIRY_WARNING_DAYS)) => 'warning',
            default => null,
        };
    }
}
