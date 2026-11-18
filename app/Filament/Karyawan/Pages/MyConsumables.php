<?php

namespace App\Filament\Karyawan\Pages;

use App\Filament\Karyawan\EmployeeTablePage;
use App\Models\ConsumableTransaction;
use BackedEnum;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// Barang habis pakai yang dikeluarkan admin untuk karyawan yang login.
class MyConsumables extends EmployeeTablePage
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedInboxArrowDown;

    protected static ?int $navigationSort = 3;

    protected static ?string $slug = 'barang-diterima';

    public static function getNavigationLabel(): string
    {
        return __('Barang diterima');
    }

    public function table(Table $table): Table
    {
        $employee = auth()->user()->employee;

        return $this->emptyState($table, __('Belum ada barang yang Anda terima'))
            ->query(fn (): Builder => ConsumableTransaction::withoutGlobalScope('consumable')
                ->with(['consumable' => fn (BelongsTo $query) => $query->withoutGlobalScopes()])
                ->where('employee_id', $employee?->getKey() ?? 0))
            ->defaultSort('tanggal', 'desc')
            ->columns([
                TextColumn::make('tanggal')
                    ->label(__('Tanggal'))
                    ->date('j M Y'),
                TextColumn::make('consumable.nama')
                    ->label(__('Barang')),
                TextColumn::make('jumlah')
                    ->label(__('Jumlah'))
                    ->formatStateUsing(fn (ConsumableTransaction $record): string => "{$record->jumlah} {$record->consumable->satuan}"),
                TextColumn::make('catatan')
                    ->label(__('Catatan'))
                    ->placeholder('—')
                    ->wrap(),
            ]);
    }
}
