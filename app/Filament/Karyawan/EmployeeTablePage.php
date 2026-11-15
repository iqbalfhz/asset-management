<?php

namespace App\Filament\Karyawan;

use Filament\Pages\Page;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Schema;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;

// Halaman panel karyawan yang isinya satu tabel data milik karyawan yang login (di luar folder Pages supaya tidak terdaftar sebagai halaman).
abstract class EmployeeTablePage extends Page implements HasTable
{
    use InteractsWithTable;

    public function getTitle(): string
    {
        return static::getNavigationLabel();
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([EmbeddedTable::make()]);
    }

    // Tabel kosong menjelaskan penyebabnya kalau akun belum terhubung ke data karyawan.
    protected function emptyState(Table $table, string $heading): Table
    {
        return $table
            ->emptyStateHeading($heading)
            ->emptyStateDescription(auth()->user()->employee === null ? __('Akun Anda belum terhubung ke data karyawan. Hubungi admin.') : null);
    }
}
