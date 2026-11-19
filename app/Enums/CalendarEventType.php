<?php

namespace App\Enums;

use App\Filament\Resources\Assets\AssetResource;
use App\Filament\Resources\Licenses\LicenseResource;
use App\Models\Asset;
use App\Models\AssetBooking;
use App\Models\AssetReminder;
use App\Models\Assignment;
use App\Models\License;
use App\Models\Maintenance;
use Carbon\CarbonInterface;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

enum CalendarEventType: string implements HasColor, HasLabel
{
    case Kembali = 'kembali';
    case Perbaikan = 'perbaikan';
    case Audit = 'audit';
    case Lisensi = 'lisensi';
    case Pengingat = 'pengingat';
    case Peminjaman = 'peminjaman';

    public function getLabel(): string
    {
        return match ($this) {
            self::Kembali => __('Batas kembali'),
            self::Perbaikan => __('Perbaikan'),
            self::Audit => __('Jadwal audit'),
            self::Lisensi => __('Lisensi habis'),
            self::Pengingat => __('Pengingat'),
            self::Peminjaman => __('Peminjaman'),
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Kembali => 'warning',
            self::Perbaikan => 'info',
            self::Audit => 'gray',
            self::Lisensi => 'danger',
            self::Pengingat => 'primary',
            self::Peminjaman => 'success',
        };
    }

    /**
     * Model yang izin lihatnya menentukan apakah jenis ini tampil.
     *
     * @return class-string
     */
    public function model(): string
    {
        return match ($this) {
            self::Kembali => Assignment::class,
            self::Perbaikan => Maintenance::class,
            self::Audit => Asset::class,
            self::Lisensi => License::class,
            self::Pengingat => Asset::class,
            self::Peminjaman => AssetBooking::class,
        };
    }

    /**
     * Jadwal jenis ini di antara dua tanggal; hak lihat ikut global scope modelnya.
     *
     * @return Collection<int, array{date: CarbonInterface, type: self, label: string, detail: string, url: string}>
     */
    public function entriesBetween(CarbonInterface $start, CarbonInterface $end): Collection
    {
        $range = [$start->toDateString(), $end->toDateString()];

        return match ($this) {
            self::Kembali => Assignment::query()
                ->with('asset')
                ->where('jenis', AssignmentType::Keluar)
                ->whereBetween('expected_return_at', $range)
                ->stillWithTarget()
                ->get()
                ->map(fn (Assignment $assignment): array => $this->entry($assignment->expected_return_at, $assignment->asset, __('Dipinjam :pemegang', ['pemegang' => $assignment->targetLabel()]))),
            self::Perbaikan => Maintenance::query()
                ->with('asset')
                ->where('status', '!=', MaintenanceStatus::Ditolak)
                ->whereBetween('tgl_mulai', $range)
                ->get()
                ->map(fn (Maintenance $maintenance): array => $this->entry($maintenance->tgl_mulai, $maintenance->asset, "{$maintenance->status->getLabel()}: ".Str::limit($maintenance->keluhan, 60))),
            self::Audit => Asset::query()
                ->auditable()
                ->whereBetween('next_audit_date', $range)
                ->get()
                ->map(fn (Asset $asset): array => $this->entry($asset->next_audit_date, $asset, $asset->nama)),
            self::Lisensi => License::query()
                ->whereBetween('tgl_habis', $range)
                ->get()
                ->map(fn (License $license): array => [
                    'date' => $license->tgl_habis,
                    'type' => $this,
                    'label' => $license->nama_software,
                    'detail' => $this->getLabel(),
                    'url' => LicenseResource::getUrl('view', ['record' => $license]),
                ]),
            self::Pengingat => AssetReminder::query()
                ->with('asset')
                ->whereNull('selesai_pada')
                ->whereBetween('tanggal', $range)
                ->get()
                ->map(fn (AssetReminder $reminder): array => $this->entry($reminder->tanggal, $reminder->asset, $reminder->jenis)),
            self::Peminjaman => AssetBooking::query()
                ->with(['asset', 'employee'])
                ->active()
                ->whereBetween('mulai', [$start->copy()->startOfDay(), $end->copy()->endOfDay()])
                ->get()
                ->map(fn (AssetBooking $booking): array => $this->entry($booking->mulai, $booking->asset, "{$booking->employee->nama}, {$booking->period()}")),
        };
    }

    /**
     * @return array{date: CarbonInterface, type: self, label: string, detail: string, url: string}
     */
    private function entry(CarbonInterface $date, Asset $asset, string $detail): array
    {
        return [
            'date' => $date,
            'type' => $this,
            'label' => $asset->kode,
            'detail' => $detail,
            'url' => AssetResource::getUrl('view', ['record' => $asset]),
        ];
    }
}
