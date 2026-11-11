<?php

namespace App\Filament\Imports;

use App\Filament\Imports\Concerns\HasCompanyColumn;
use App\Models\Asset;
use App\Models\AssetModel;
use App\Models\Category;
use App\Models\Room;
use App\Models\Status;
use DateTimeImmutable;
use Filament\Actions\Imports\Exceptions\RowImportFailedException;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\Models\Import;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Number;

class AssetImporter extends Importer
{
    use HasCompanyColumn;

    protected static ?string $model = Asset::class;

    public static function getColumns(): array
    {
        return [
            static::companyColumn(),
            ImportColumn::make('nama')
                ->label(__('Nama'))
                ->exampleHeader('nama')
                ->example('Laptop Staf Keuangan')
                ->requiredMapping()
                ->rules(['required', 'max:255']),
            ImportColumn::make('category')
                ->label(__('Kategori'))
                ->exampleHeader('kategori')
                ->example('LPT')
                ->helperText(__('Nama atau kode kategori.'))
                ->requiredMapping()
                ->relationship(resolveUsing: fn (string $state): ?Category => static::category($state))
                ->rules(['required']),
            ImportColumn::make('assetModel')
                ->label(__('Model aset'))
                ->exampleHeader('model')
                ->example('Dell Latitude 5440')
                ->helperText(__('Nama model, atau produsen + model.'))
                ->guesses(['model'])
                ->relationship(resolveUsing: fn (string $state, array $data): ?AssetModel => static::assetModel($state, $data['category'] ?? null))
                ->ignoreBlankState(),
            ImportColumn::make('status')
                ->label(__('Status awal'))
                ->exampleHeader('status')
                ->example(Status::DISIAPKAN)
                ->helperText(__('Disiapkan atau Tersedia. Kosong = Disiapkan.'))
                ->relationship(resolveUsing: fn (string $state): ?Status => static::initialStatus($state))
                ->ignoreBlankState(),
            ImportColumn::make('no_seri')
                ->label(__('Nomor seri'))
                ->exampleHeader('nomor_seri')
                ->example('5CD1234XYZ')
                ->guesses(['nomor_seri', 'serial', 'serial number'])
                ->rules(['max:255']),
            ImportColumn::make('gedung')
                ->label(__('Gedung'))
                ->example('Gedung A')
                ->fillRecordUsing(fn (): null => null),
            ImportColumn::make('lantai')
                ->label(__('Lantai'))
                ->example('Lantai 2')
                ->fillRecordUsing(fn (): null => null),
            ImportColumn::make('ruang')
                ->label(__('Ruang'))
                ->example('Ruang Keuangan')
                ->helperText(__('Isi gedung, lantai, dan ruang persis seperti master data.'))
                ->fillRecordUsing(fn (): null => null),
            ImportColumn::make('supplier')
                ->label(__('Supplier'))
                ->example('PT Sumber Komputer')
                ->relationship(resolveUsing: 'nama')
                ->ignoreBlankState(),
            ImportColumn::make('order_number')
                ->label(__('Nomor PO'))
                ->exampleHeader('nomor_po')
                ->example('PO-2026-014')
                ->rules(['max:255']),
            ImportColumn::make('tgl_beli')
                ->label(__('Tanggal beli'))
                ->exampleHeader('tanggal_beli')
                ->example('31/01/2026')
                ->castStateUsing(fn (?string $state): ?string => static::date($state))
                ->rules(['nullable', 'date']),
            ImportColumn::make('harga_beli')
                ->label(__('Harga beli'))
                ->exampleHeader('harga_beli')
                ->example('12.500.000')
                ->castStateUsing(fn (?string $state): ?string => static::rupiah($state))
                ->rules(['nullable', 'integer', 'min:0']),
            ImportColumn::make('depreciation')
                ->label(__('Kelas penyusutan'))
                ->exampleHeader('penyusutan')
                ->helperText(__('Kosong = ikut model aset.'))
                ->guesses(['penyusutan'])
                ->relationship(resolveUsing: 'nama')
                ->ignoreBlankState(),
            ImportColumn::make('garansi_sampai')
                ->label(__('Garansi sampai'))
                ->exampleHeader('garansi_sampai')
                ->example('31/01/2029')
                ->castStateUsing(fn (?string $state): ?string => static::date($state))
                ->rules(['nullable', 'date']),
            ImportColumn::make('eol_date')
                ->label(__('Tanggal EOL'))
                ->exampleHeader('tanggal_eol')
                ->guesses(['tanggal_eol', 'eol'])
                ->castStateUsing(fn (?string $state): ?string => static::date($state))
                ->rules(['nullable', 'date']),
            ImportColumn::make('spesifikasi')
                ->label(__('Spesifikasi'))
                ->exampleHeader('spesifikasi')
                ->example('RAM: 16 GB; SSD: 512 GB')
                ->helperText(__('Pasangan kunci: nilai, dipisah titik koma.'))
                ->castStateUsing(fn (?string $state): ?array => static::specifications($state))
                ->rules(['nullable', 'array']),
            ImportColumn::make('is_byod')
                ->label(__('BYOD'))
                ->exampleHeader('byod')
                ->example('tidak')
                ->castStateUsing(fn (?string $state): bool|string|null => static::yesNo($state))
                ->rules(['nullable', 'boolean'])
                ->ignoreBlankState(),
            ImportColumn::make('notes')
                ->label(__('Catatan'))
                ->exampleHeader('catatan')
                ->guesses(['catatan'])
                ->rules(['max:65535']),
        ];
    }

    public function resolveRecord(): Asset
    {
        return new Asset;
    }

    protected function afterFill(): void
    {
        $this->record->company_id ??= $this->companyId();

        if (blank($this->record->company_id)) {
            throw new RowImportFailedException(__('Isi kolom perusahaan.'));
        }

        $room = $this->room();
        $this->record->room_id = $room?->getKey();
        $this->record->default_room_id = $room?->getKey();

        $template = $this->record->category->template_spesifikasi ?? [];
        $this->record->spesifikasi = array_merge(array_fill_keys($template, ''), $this->record->spesifikasi ?? []) ?: null;

        if ($this->record->assetModel?->wajib_nomor_seri && blank($this->record->no_seri)) {
            throw new RowImportFailedException(__('Nomor seri wajib diisi untuk model :model.', ['model' => $this->record->assetModel->nama]));
        }
    }

    public static function getCompletedNotificationTitle(Import $import): string
    {
        return __('Import aset selesai');
    }

    public static function getCompletedNotificationBody(Import $import): string
    {
        $body = __(':count aset berhasil diimport.', ['count' => Number::format($import->successful_rows, locale: 'id')]);

        if ($failedRowsCount = $import->getFailedRowsCount()) {
            $body .= ' '.__(':count baris gagal; unduh daftarnya untuk melihat alasannya.', ['count' => Number::format($failedRowsCount, locale: 'id')]);
        }

        return $body;
    }

    private function room(): ?Room
    {
        $names = array_map(fn (string $key): ?string => $this->data[$key] ?? null, ['gedung', 'lantai', 'ruang']);

        if (array_filter($names) === []) {
            return null;
        }

        [$building, $floor, $room] = $names;
        $companyId = $this->record->company_id;

        $found = Room::query()
            ->where('nama', $room)
            ->whereHas('floor', fn (Builder $query) => $query
                ->where('nama', $floor)
                ->whereHas('building', fn (Builder $query) => $query->where('nama', $building)->where('company_id', $companyId)))
            ->first();

        return $found ?? throw new RowImportFailedException(__('Lokasi :lokasi tidak ditemukan di perusahaan ini.', ['lokasi' => implode(' — ', array_map('strval', $names))]));
    }

    private static function category(string $nameOrCode): ?Category
    {
        return Category::query()
            ->managedByCurrentUser()
            ->where(fn (Builder $query) => $query->where('nama', $nameOrCode)->orWhere('kode', strtoupper($nameOrCode)))
            ->first();
    }

    private static function assetModel(string $name, ?string $category): ?AssetModel
    {
        $categoryId = filled($category) ? static::category($category)?->getKey() : null;

        $matches = AssetModel::query()
            ->with('manufacturer')
            ->where('category_id', $categoryId)
            ->get()
            ->filter(fn (AssetModel $model): bool => strcasecmp($model->nama, $name) === 0
                || strcasecmp("{$model->manufacturer->nama} {$model->nama}", $name) === 0);

        // Nama model yang sama dari dua produsen harus ditulis lengkap dengan produsennya.
        return $matches->count() === 1 ? $matches->first() : null;
    }

    private static function initialStatus(string $name): ?Status
    {
        return Status::query()
            ->whereIn('nama', [Status::DISIAPKAN, Status::TERSEDIA])
            ->get()
            ->first(fn (Status $status): bool => strcasecmp($status->nama, $name) === 0);
    }

    // Tanggal Indonesia (31/01/2026 atau 31-01-2026) diubah ke Y-m-d; format lain dibiarkan untuk divalidasi.
    private static function date(?string $state): ?string
    {
        if (blank($state)) {
            return null;
        }

        foreach (['d/m/Y', 'd-m-Y', 'Y-m-d'] as $format) {
            $date = DateTimeImmutable::createFromFormat("!{$format}", $state);

            // Dicocokkan balik supaya tanggal yang tidak ada (31/02/2026) ikut ditolak.
            if ($date !== false && $date->format($format) === $state) {
                return $date->format('Y-m-d');
            }
        }

        return $state;
    }

    // "Rp 12.500.000" atau "12500000,00" menjadi "12500000".
    private static function rupiah(?string $state): ?string
    {
        if (blank($state)) {
            return null;
        }

        $withoutCents = preg_replace('/[.,]\d{2}$/', '', $state);
        $digits = preg_replace('/[^0-9]/', '', $withoutCents);

        return $digits === '' ? $state : $digits;
    }

    /**
     * @return array<string, string>|null
     */
    private static function specifications(?string $state): ?array
    {
        if (blank($state)) {
            return null;
        }

        $specifications = [];

        foreach (explode(';', $state) as $pair) {
            [$key, $value] = array_pad(explode(':', $pair, 2), 2, '');

            if (filled(trim($key))) {
                $specifications[trim($key)] = trim($value);
            }
        }

        return $specifications;
    }

    private static function yesNo(?string $state): bool|string|null
    {
        return match (strtolower(trim((string) $state))) {
            '' => null,
            'ya', 'y', 'yes', 'true', '1' => true,
            'tidak', 't', 'no', 'n', 'false', '0' => false,
            default => $state,
        };
    }
}
