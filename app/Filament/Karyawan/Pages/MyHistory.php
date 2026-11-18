<?php

namespace App\Filament\Karyawan\Pages;

use App\Filament\Karyawan\EmployeeTablePage;
use App\Models\Assignment;
use App\Models\Employee;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MyHistory extends EmployeeTablePage
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClock;

    protected static ?int $navigationSort = 4;

    protected static ?string $slug = 'riwayat';

    public static function getNavigationLabel(): string
    {
        return __('Riwayat');
    }

    public function getTitle(): string
    {
        return __('Riwayat serah-terima');
    }

    public function table(Table $table): Table
    {
        $employee = auth()->user()->employee;

        return $this->emptyState($table, __('Belum ada riwayat serah-terima'))
            ->query(fn (): Builder => Assignment::withoutGlobalScope('asset')
                ->with(['asset' => fn (BelongsTo $query) => $query->withoutGlobalScopes()])
                ->where('target_type', (new Employee)->getMorphClass())
                ->where('target_id', $employee?->getKey() ?? 0))
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('tanggal')
                    ->label(__('Tanggal'))
                    ->date('j M Y'),
                TextColumn::make('asset.kode')
                    ->label(__('Aset'))
                    ->description(fn (Assignment $record): string => $record->asset->nama),
                TextColumn::make('jenis')
                    ->label(__('Jenis'))
                    ->badge(),
                TextColumn::make('kondisi')
                    ->label(__('Kondisi')),
                TextColumn::make('no_bast')
                    ->label(__('Nomor BAST')),
                TextColumn::make('status')
                    ->label(__('Status'))
                    ->badge(),
                TextColumn::make('accepted_at')
                    ->label(__('Diterima pada'))
                    ->dateTime('j M Y H:i')
                    ->placeholder('—'),
            ])
            ->recordActions([
                Action::make('bast')
                    ->label(__('BAST'))
                    ->icon(Heroicon::OutlinedDocumentArrowDown)
                    ->url(fn (Assignment $record): string => route('filament.karyawan.bast.download', $record))
                    ->openUrlInNewTab(),
            ]);
    }
}
