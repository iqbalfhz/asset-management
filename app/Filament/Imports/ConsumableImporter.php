<?php

namespace App\Filament\Imports;

use App\Filament\Imports\Concerns\HasCompanyColumn;
use App\Filament\Imports\Concerns\HasManagingRoleColumn;
use App\Models\Consumable;
use Filament\Actions\Imports\Exceptions\RowImportFailedException;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\Models\Import;
use Illuminate\Support\Number;

class ConsumableImporter extends Importer
{
    use HasCompanyColumn, HasManagingRoleColumn;

    protected static ?string $model = Consumable::class;

    public static function getColumns(): array
    {
        return [
            static::companyColumn(),
            ImportColumn::make('nama')
                ->label(__('Nama'))
                ->example('Kertas A4')
                ->requiredMapping()
                ->rules(['required', 'max:255']),
            ImportColumn::make('satuan')
                ->label(__('Satuan'))
                ->example('rim')
                ->requiredMapping()
                ->rules(['required', 'max:30']),
            static::managingRoleColumn(),
            ImportColumn::make('stok_minimum')
                ->label(__('Batas minimum'))
                ->exampleHeader('stok_minimum')
                ->example('5')
                ->helperText(__('Kosong = 0 (tidak dipantau).'))
                ->rules(['nullable', 'integer', 'min:0'])
                ->ignoreBlankState(),
            ImportColumn::make('stok_awal')
                ->label(__('Stok awal'))
                ->exampleHeader('stok_awal')
                ->example('40')
                ->helperText(__('Dicatat sebagai transaksi masuk.'))
                ->rules(['nullable', 'integer', 'min:0'])
                ->fillRecordUsing(fn (): null => null),
            ImportColumn::make('catatan')
                ->label(__('Catatan'))
                ->rules(['max:2000']),
        ];
    }

    // Nama unik per perusahaan, termasuk barang milik role pengelola lain yang tidak terlihat oleh admin ini.
    public function resolveRecord(): Consumable
    {
        $companyId = $this->companyId();

        if (Consumable::withoutGlobalScopes()->where('company_id', $companyId)->where('nama', $this->data['nama'] ?? null)->exists()) {
            throw new RowImportFailedException(__('Barang :nama sudah ada.', ['nama' => $this->data['nama']]));
        }

        return new Consumable(['company_id' => $companyId]);
    }

    protected function afterFill(): void
    {
        if (blank($this->record->company_id)) {
            throw new RowImportFailedException(__('Isi kolom perusahaan.'));
        }

        $this->fillDefaultManagingRole();
    }

    // Stok hanya berubah lewat transaksi, sama seperti stok awal di form.
    protected function afterCreate(): void
    {
        $initialStock = (int) ($this->data['stok_awal'] ?? 0);

        if ($initialStock > 0) {
            $this->record->receive($initialStock, ['catatan' => __('Stok awal dari import')]);
        }
    }

    public static function getCompletedNotificationTitle(Import $import): string
    {
        return __('Import barang habis pakai selesai');
    }

    public static function getCompletedNotificationBody(Import $import): string
    {
        $body = __(':count barang berhasil diimport.', ['count' => Number::format($import->successful_rows, locale: 'id')]);

        if ($failedRowsCount = $import->getFailedRowsCount()) {
            $body .= ' '.__(':count baris gagal; unduh daftarnya untuk melihat alasannya.', ['count' => Number::format($failedRowsCount, locale: 'id')]);
        }

        return $body;
    }
}
