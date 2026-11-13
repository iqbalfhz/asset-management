<?php

namespace App\Filament\Karyawan\Pages;

use App\Models\ConsumableTransaction;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// Barang habis pakai yang dikeluarkan admin untuk karyawan yang login.
class MyConsumables extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedInboxArrowDown;

    protected static ?int $navigationSort = 3;

    protected static ?string $slug = 'barang-diterima';

    public static function getNavigationLabel(): string
    {
        return __('Barang diterima');
    }

    public function getTitle(): string
    {
        return __('Barang diterima');
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([EmbeddedTable::make()]);
    }

    public function table(Table $table): Table
    {
        $employee = auth()->user()->employee;

        return $table
            ->query(fn (): Builder => ConsumableTransaction::withoutGlobalScope('consumable')
                ->with(['consumable' => fn (BelongsTo $query) => $query->withoutGlobalScopes()])
                ->where('employee_id', $employee?->getKey() ?? 0))
            ->defaultSort('tanggal', 'desc')
            ->emptyStateHeading(__('Belum ada barang yang Anda terima'))
            ->emptyStateDescription($employee === null ? __('Akun Anda belum terhubung ke data karyawan. Hubungi admin.') : null)
            ->columns([
                TextColumn::make('tanggal')
                    ->date('j M Y'),
                TextColumn::make('consumable.nama')
                    ->label(__('Barang')),
                TextColumn::make('jumlah')
                    ->formatStateUsing(fn (ConsumableTransaction $record): string => "{$record->jumlah} {$record->consumable->satuan}"),
                TextColumn::make('catatan')
                    ->placeholder('—')
                    ->wrap(),
            ]);
    }
}
