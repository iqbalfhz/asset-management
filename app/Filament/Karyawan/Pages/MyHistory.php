<?php

namespace App\Filament\Karyawan\Pages;

use App\Models\Assignment;
use App\Models\Employee;
use BackedEnum;
use Filament\Actions\Action;
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

class MyHistory extends Page implements HasTable
{
    use InteractsWithTable;

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

    public function content(Schema $schema): Schema
    {
        return $schema->components([EmbeddedTable::make()]);
    }

    public function table(Table $table): Table
    {
        $employee = auth()->user()->employee;

        return $table
            ->query(fn (): Builder => Assignment::withoutGlobalScope('asset')
                ->with(['asset' => fn (BelongsTo $query) => $query->withoutGlobalScopes()])
                ->where('target_type', (new Employee)->getMorphClass())
                ->where('target_id', $employee?->getKey() ?? 0))
            ->defaultSort('id', 'desc')
            ->emptyStateHeading(__('Belum ada riwayat serah-terima'))
            ->columns([
                TextColumn::make('tanggal')
                    ->date('j M Y'),
                TextColumn::make('asset.kode')
                    ->label(__('Aset'))
                    ->description(fn (Assignment $record): string => $record->asset->nama),
                TextColumn::make('jenis')
                    ->badge(),
                TextColumn::make('kondisi'),
                TextColumn::make('no_bast')
                    ->label(__('Nomor BAST')),
                TextColumn::make('status')
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
