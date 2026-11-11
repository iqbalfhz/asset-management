<?php

namespace App\Filament\Imports;

use App\Filament\Imports\Concerns\HasCompanyColumn;
use App\Models\Department;
use App\Models\Employee;
use Filament\Actions\Imports\Exceptions\RowImportFailedException;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\Models\Import;
use Filament\Forms\Components\Checkbox;
use Illuminate\Support\Number;

class EmployeeImporter extends Importer
{
    use HasCompanyColumn;

    protected static ?string $model = Employee::class;

    public static function getColumns(): array
    {
        return [
            static::companyColumn(),
            ImportColumn::make('nik')
                ->label(__('NIK'))
                ->exampleHeader('nik')
                ->example('2026001')
                ->requiredMapping()
                ->rules(['required', 'max:50']),
            ImportColumn::make('nama')
                ->label(__('Nama'))
                ->exampleHeader('nama')
                ->example('Rina Wulandari')
                ->requiredMapping()
                ->rules(['required', 'max:255']),
            ImportColumn::make('jabatan')
                ->label(__('Jabatan'))
                ->exampleHeader('jabatan')
                ->example('Staf Keuangan')
                ->rules(['max:255'])
                ->ignoreBlankState(),
            ImportColumn::make('department')
                ->label(__('Departemen'))
                ->exampleHeader('departemen')
                ->example('Keuangan')
                ->guesses(['departemen'])
                ->relationship(resolveUsing: fn (string $state, EmployeeImporter $importer): ?Department => Department::query()
                    ->where('company_id', $importer->companyId())
                    ->where('nama', $state)
                    ->first())
                ->ignoreBlankState(),
        ];
    }

    public static function getOptionsFormComponents(): array
    {
        return [
            Checkbox::make('updateExisting')
                ->label(__('Perbarui karyawan yang NIK-nya sudah ada')),
        ];
    }

    public function resolveRecord(): Employee
    {
        $companyId = $this->companyId();
        $employee = Employee::query()->where('company_id', $companyId)->where('nik', $this->data['nik'] ?? null)->first();

        if ($employee === null) {
            return new Employee(['company_id' => $companyId]);
        }

        if (! ($this->options['updateExisting'] ?? false)) {
            throw new RowImportFailedException(__('NIK :nik sudah terdaftar.', ['nik' => $employee->nik]));
        }

        return $employee;
    }

    protected function afterFill(): void
    {
        if (blank($this->record->company_id)) {
            throw new RowImportFailedException(__('Isi kolom perusahaan.'));
        }
    }

    public static function getCompletedNotificationTitle(Import $import): string
    {
        return __('Import karyawan selesai');
    }

    public static function getCompletedNotificationBody(Import $import): string
    {
        $body = __(':count karyawan berhasil diimport.', ['count' => Number::format($import->successful_rows, locale: 'id')]);

        if ($failedRowsCount = $import->getFailedRowsCount()) {
            $body .= ' '.__(':count baris gagal; unduh daftarnya untuk melihat alasannya.', ['count' => Number::format($failedRowsCount, locale: 'id')]);
        }

        return $body;
    }
}
