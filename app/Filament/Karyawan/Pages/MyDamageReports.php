<?php

namespace App\Filament\Karyawan\Pages;

use App\Filament\Karyawan\Actions\ReportDamageAction;
use App\Filament\Karyawan\EmployeeTablePage;
use App\Models\Maintenance;
use BackedEnum;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MyDamageReports extends EmployeeTablePage
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedWrenchScrewdriver;

    protected static ?int $navigationSort = 5;

    protected static ?string $slug = 'lapor-kerusakan';

    public static function getNavigationLabel(): string
    {
        return __('Lapor kerusakan');
    }

    public function getTitle(): string
    {
        return __('Laporan kerusakan');
    }

    protected function getHeaderActions(): array
    {
        return [
            ReportDamageAction::chooseAsset(),
        ];
    }

    public function table(Table $table): Table
    {
        $employee = auth()->user()->employee;

        return $this->emptyState($table, __('Belum ada laporan kerusakan'))
            ->query(fn (): Builder => Maintenance::withoutGlobalScopes()
                ->with(['asset' => fn (BelongsTo $query) => $query->withoutGlobalScopes()])
                ->where('dilapor_oleh', $employee?->getKey() ?? 0))
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('created_at')
                    ->label(__('Dilaporkan'))
                    ->sortable()
                    ->dateTime('j M Y H:i'),
                TextColumn::make('asset.kode')
                    ->label(__('Aset'))
                    ->sortable()
                    ->description(fn (Maintenance $record): string => $record->asset->nama),
                TextColumn::make('keluhan')
                    ->label(__('Keluhan'))
                    ->limit(80)
                    ->wrap(),
                TextColumn::make('status')
                    ->label(__('Status'))
                    ->sortable()
                    ->badge(),
                TextColumn::make('catatan_admin')
                    ->label(__('Catatan admin'))
                    ->placeholder('—')
                    ->wrap(),
            ]);
    }
}
