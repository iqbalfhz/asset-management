<?php

namespace App\Filament\Tables\Filters;

use Filament\Forms\Components\DatePicker;
use Filament\Tables\Filters\Filter;
use Illuminate\Database\Eloquent\Builder;

class DateRangeFilter
{
    // Rentang tanggal kejadian (dari, sampai) di halaman log.
    public static function make(string $column = 'created_at'): Filter
    {
        return Filter::make('tanggal')
            ->schema([
                DatePicker::make('dari')
                    ->label(__('Dari tanggal')),
                DatePicker::make('sampai')
                    ->label(__('Sampai tanggal')),
            ])
            ->query(fn (Builder $query, array $data): Builder => $query
                ->when($data['dari'] ?? null, fn (Builder $query, string $date) => $query->whereDate($column, '>=', $date))
                ->when($data['sampai'] ?? null, fn (Builder $query, string $date) => $query->whereDate($column, '<=', $date)));
    }
}
