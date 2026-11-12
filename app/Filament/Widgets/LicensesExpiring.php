<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Licenses\LicenseResource;
use App\Filament\Resources\Licenses\Tables\LicensesTable;
use App\Models\License;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class LicensesExpiring extends TableWidget
{
    protected static ?int $sort = 5;

    public static function canView(): bool
    {
        return auth()->user()?->can('viewAny', License::class) ?? false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading(__('Lisensi habis dalam :hari hari', ['hari' => License::EXPIRY_WARNING_DAYS]))
            ->query(License::query()->expiringSoon()->withCount('activeSeats'))
            ->defaultSort('tgl_habis')
            ->paginated([5])
            ->emptyStateHeading(__('Tidak ada lisensi yang segera habis'))
            ->recordUrl(fn (License $record): string => LicenseResource::getUrl('view', ['record' => $record]))
            ->columns([
                TextColumn::make('nama_software')
                    ->label(__('Software')),
                TextColumn::make('active_seats_count')
                    ->label(__('Seat terpakai'))
                    ->formatStateUsing(fn (int $state, License $record): string => "{$state} / {$record->jumlah_seat}"),
                TextColumn::make('tgl_habis')
                    ->label(__('Tanggal habis'))
                    ->date('j M Y')
                    ->color(fn (License $record): ?string => LicensesTable::expiryColor($record)),
            ]);
    }
}
