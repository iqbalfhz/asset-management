<?php

namespace App\Filament\Karyawan\Pages;

use App\Filament\Karyawan\Actions\ReportDamageAction;
use App\Models\Maintenance;
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

class MyDamageReports extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedWrenchScrewdriver;

    protected static ?int $navigationSort = 3;

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

    public function content(Schema $schema): Schema
    {
        return $schema->components([EmbeddedTable::make()]);
    }

    public function table(Table $table): Table
    {
        $employee = auth()->user()->employee;

        return $table
            ->query(fn (): Builder => Maintenance::withoutGlobalScopes()
                ->with(['asset' => fn (BelongsTo $query) => $query->withoutGlobalScopes()])
                ->where('dilapor_oleh', $employee?->getKey() ?? 0))
            ->defaultSort('id', 'desc')
            ->emptyStateHeading(__('Belum ada laporan kerusakan'))
            ->columns([
                TextColumn::make('created_at')
                    ->label(__('Dilaporkan'))
                    ->dateTime('j M Y H:i'),
                TextColumn::make('asset.kode')
                    ->label(__('Aset'))
                    ->description(fn (Maintenance $record): string => $record->asset->nama),
                TextColumn::make('keluhan')
                    ->limit(80)
                    ->wrap(),
                TextColumn::make('status')
                    ->badge(),
                TextColumn::make('catatan_admin')
                    ->label(__('Catatan admin'))
                    ->placeholder('—')
                    ->wrap(),
            ]);
    }
}
