<?php

namespace App\Filament\Imports;

use App\Filament\Imports\Concerns\CastsIndonesianFormats;
use App\Filament\Imports\Concerns\HasCompanyColumn;
use App\Filament\Imports\Concerns\HasManagingRoleColumn;
use App\Models\License;
use Filament\Actions\Imports\Exceptions\RowImportFailedException;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\Models\Import;
use Illuminate\Support\Number;

// Product key sengaja tidak ada: baris gagal disimpan Filament sebagai teks biasa, padahal product key wajib terenkripsi.
class LicenseImporter extends Importer
{
    use CastsIndonesianFormats, HasCompanyColumn, HasManagingRoleColumn;

    protected static ?string $model = License::class;

    public static function getColumns(): array
    {
        return [
            static::companyColumn(),
            ImportColumn::make('nama_software')
                ->label(__('Nama software'))
                ->exampleHeader('nama_software')
                ->example('Microsoft 365 Business')
                ->guesses(['software', 'nama'])
                ->requiredMapping()
                ->rules(['required', 'max:255']),
            static::managingRoleColumn(),
            ImportColumn::make('jumlah_seat')
                ->label(__('Jumlah seat'))
                ->exampleHeader('jumlah_seat')
                ->example('25')
                ->guesses(['seat'])
                ->requiredMapping()
                ->rules(['required', 'integer', 'min:1']),
            ImportColumn::make('tgl_habis')
                ->label(__('Tanggal habis'))
                ->exampleHeader('tanggal_habis')
                ->example('31/12/2026')
                ->helperText(__('Kosong = tanpa masa berlaku.'))
                ->guesses(['tanggal_habis'])
                ->castStateUsing(fn (?string $state): ?string => static::date($state))
                ->rules(['nullable', 'date']),
            ImportColumn::make('harga')
                ->label(__('Harga'))
                ->example('37.500.000')
                ->castStateUsing(fn (?string $state): ?string => static::rupiah($state))
                ->rules(['nullable', 'integer', 'min:0']),
            ImportColumn::make('supplier')
                ->label(__('Supplier'))
                ->example('PT Sumber Software')
                ->relationship(resolveUsing: 'nama')
                ->ignoreBlankState(),
            ImportColumn::make('catatan')
                ->label(__('Catatan'))
                ->rules(['max:2000']),
        ];
    }

    public function resolveRecord(): License
    {
        return new License;
    }

    protected function afterFill(): void
    {
        $this->record->company_id ??= $this->companyId();

        if (blank($this->record->company_id)) {
            throw new RowImportFailedException(__('Isi kolom perusahaan.'));
        }

        $this->fillDefaultManagingRole();
    }

    public static function getCompletedNotificationTitle(Import $import): string
    {
        return __('Import lisensi selesai');
    }

    public static function getCompletedNotificationBody(Import $import): string
    {
        $body = __(':count lisensi berhasil diimport.', ['count' => Number::format($import->successful_rows, locale: 'id')]);

        if ($failedRowsCount = $import->getFailedRowsCount()) {
            $body .= ' '.__(':count baris gagal; unduh daftarnya untuk melihat alasannya.', ['count' => Number::format($failedRowsCount, locale: 'id')]);
        }

        return $body;
    }
}
