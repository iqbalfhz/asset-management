<?php

namespace App\Filament\Karyawan\Pages;

use App\Filament\Karyawan\EmployeeTablePage;
use App\Filament\Tables\LicenseSeatTable;
use App\Models\LicenseSeat;
use BackedEnum;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// Seat lisensi yang sedang dipasang untuk karyawan yang login; product key tidak ditampilkan.
class MyLicenses extends EmployeeTablePage
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedKey;

    protected static ?int $navigationSort = 2;

    protected static ?string $slug = 'lisensi-saya';

    public static function getNavigationLabel(): string
    {
        return __('Lisensi saya');
    }

    public function table(Table $table): Table
    {
        $employee = auth()->user()->employee;

        return $this->emptyState($table, __('Belum ada lisensi yang dipasang untuk Anda'))
            ->query(fn (): Builder => LicenseSeat::withoutGlobalScope('license')
                ->with(['license' => fn (BelongsTo $query) => $query->withoutGlobalScopes()])
                ->where('employee_id', $employee?->getKey() ?? 0)
                ->whereNull('dilepas_pada'))
            ->defaultSort('dipasang_pada', 'desc')
            ->columns([
                LicenseSeatTable::software(),
                LicenseSeatTable::installedAt(),
                LicenseSeatTable::expiresAt(),
            ]);
    }
}
