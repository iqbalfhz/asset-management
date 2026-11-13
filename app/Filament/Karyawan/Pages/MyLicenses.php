<?php

namespace App\Filament\Karyawan\Pages;

use App\Models\LicenseSeat;
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

// Seat lisensi yang sedang dipasang untuk karyawan yang login; product key tidak ditampilkan.
class MyLicenses extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedKey;

    protected static ?int $navigationSort = 2;

    protected static ?string $slug = 'lisensi-saya';

    public static function getNavigationLabel(): string
    {
        return __('Lisensi saya');
    }

    public function getTitle(): string
    {
        return __('Lisensi saya');
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([EmbeddedTable::make()]);
    }

    public function table(Table $table): Table
    {
        $employee = auth()->user()->employee;

        return $table
            ->query(fn (): Builder => LicenseSeat::withoutGlobalScope('license')
                ->with(['license' => fn (BelongsTo $query) => $query->withoutGlobalScopes()])
                ->where('employee_id', $employee?->getKey() ?? 0)
                ->whereNull('dilepas_pada'))
            ->defaultSort('dipasang_pada', 'desc')
            ->emptyStateHeading(__('Belum ada lisensi yang dipasang untuk Anda'))
            ->emptyStateDescription($employee === null ? __('Akun Anda belum terhubung ke data karyawan. Hubungi admin.') : null)
            ->columns([
                TextColumn::make('license.nama_software')
                    ->label(__('Software')),
                TextColumn::make('dipasang_pada')
                    ->label(__('Dipasang'))
                    ->date('j M Y'),
                TextColumn::make('license.tgl_habis')
                    ->label(__('Berlaku sampai'))
                    ->date('j M Y')
                    ->placeholder(__('Tanpa batas'))
                    ->color(fn (LicenseSeat $record): ?string => $record->license->isExpired() ? 'danger' : null),
            ]);
    }
}
