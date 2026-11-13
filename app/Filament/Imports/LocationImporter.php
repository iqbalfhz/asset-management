<?php

namespace App\Filament\Imports;

use App\Filament\Imports\Concerns\HasCompanyColumn;
use App\Models\Building;
use App\Models\Room;
use Filament\Actions\Imports\Exceptions\RowImportFailedException;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\Models\Import;
use Illuminate\Support\Number;

// Satu baris = satu ruang; gedung dan lantai yang belum ada dibuat sekalian, supaya master lokasi siap sebelum import aset.
class LocationImporter extends Importer
{
    use HasCompanyColumn;

    protected static ?string $model = Room::class;

    public static function getColumns(): array
    {
        return [
            static::companyColumn(),
            ImportColumn::make('gedung')
                ->label(__('Gedung'))
                ->example('Gedung A')
                ->requiredMapping()
                ->rules(['required', 'max:255'])
                ->fillRecordUsing(fn (): null => null),
            ImportColumn::make('lantai')
                ->label(__('Lantai'))
                ->example('Lantai 2')
                ->requiredMapping()
                ->rules(['required', 'max:255'])
                ->fillRecordUsing(fn (): null => null),
            ImportColumn::make('nama')
                ->label(__('Ruang'))
                ->exampleHeader('ruang')
                ->example('Ruang Keuangan')
                ->guesses(['ruang'])
                ->requiredMapping()
                ->rules(['required', 'max:255']),
        ];
    }

    public function resolveRecord(): Room
    {
        return new Room;
    }

    // Baris yang gagal tidak di-rollback oleh Filament, jadi ruang ganda dicek sebelum gedung atau lantai dibuat.
    protected function afterFill(): void
    {
        $companyId = $this->companyId();

        if (blank($companyId)) {
            throw new RowImportFailedException(__('Isi kolom perusahaan.'));
        }

        $building = Building::query()->where('company_id', $companyId)->where('nama', $this->data['gedung'])->first();
        $floor = $building?->floors()->where('nama', $this->data['lantai'])->first();

        if ($floor?->rooms()->where('nama', $this->record->nama)->exists()) {
            throw new RowImportFailedException(__('Ruang :lokasi sudah ada.', [
                'lokasi' => implode(' — ', [$this->data['gedung'], $this->data['lantai'], $this->record->nama]),
            ]));
        }

        $building ??= Building::query()->create(['company_id' => $companyId, 'nama' => $this->data['gedung']]);
        $floor ??= $building->floors()->create(['nama' => $this->data['lantai']]);

        $this->record->floor_id = $floor->getKey();
    }

    public static function getCompletedNotificationTitle(Import $import): string
    {
        return __('Import lokasi selesai');
    }

    public static function getCompletedNotificationBody(Import $import): string
    {
        $body = __(':count ruang berhasil diimport.', ['count' => Number::format($import->successful_rows, locale: 'id')]);

        if ($failedRowsCount = $import->getFailedRowsCount()) {
            $body .= ' '.__(':count baris gagal; unduh daftarnya untuk melihat alasannya.', ['count' => Number::format($failedRowsCount, locale: 'id')]);
        }

        return $body;
    }
}
