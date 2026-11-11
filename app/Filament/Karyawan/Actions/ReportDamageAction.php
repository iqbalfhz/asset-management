<?php

namespace App\Filament\Karyawan\Actions;

use App\Models\Asset;
use App\Models\Employee;
use App\Models\Maintenance;
use App\Models\Scopes\ManagedCategoryScope;
use App\Models\Status;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Storage;
use LogicException;

// Lapor kerusakan dari panel karyawan: per aset di Aset saya, atau dengan memilih aset di halaman laporan.
class ReportDamageAction
{
    public static function forAsset(): Action
    {
        return static::base()
            ->visible(fn (Asset $record): bool => $record->status->nama === Status::DIPAKAI)
            ->schema(static::fields())
            ->action(fn (Asset $record, array $data, Action $action) => static::submit($record, $data, $action));
    }

    public static function chooseAsset(): Action
    {
        return static::base()
            ->schema([
                Select::make('asset_id')
                    ->label(__('Aset'))
                    ->options(fn (): array => static::heldAssets()->mapWithKeys(fn (Asset $asset): array => [$asset->id => "{$asset->kode} — {$asset->nama}"])->all())
                    ->required(),
                ...static::fields(),
            ])
            ->action(function (array $data, Action $action): void {
                $asset = static::heldAssets()->firstWhere('id', (int) $data['asset_id']);

                static::submit($asset, $data, $action);
            });
    }

    private static function base(): Action
    {
        return Action::make('report')
            ->label(__('Lapor kerusakan'))
            ->icon(Heroicon::OutlinedWrenchScrewdriver)
            ->modalHeading(__('Lapor kerusakan'))
            ->modalDescription(__('Ceritakan masalahnya. Admin akan mengecek dan memberi kabar di halaman Lapor kerusakan.'))
            ->modalSubmitActionLabel(__('Kirim laporan'));
    }

    /**
     * @return array<int, Textarea|FileUpload>
     */
    private static function fields(): array
    {
        return [
            Textarea::make('keluhan')
                ->label(__('Keluhan'))
                ->placeholder(__('Contoh: layar berkedip sejak kemarin, kadang mati sendiri.'))
                ->rows(4)
                ->required()
                ->maxLength(2000),
            FileUpload::make('foto')
                ->label(__('Foto (opsional)'))
                ->image()
                // Tanpa SVG: SVG bisa membawa skrip saat dibuka di browser.
                ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                ->disk(Maintenance::FOTO_DISK)
                ->directory('maintenances')
                ->visibility('private')
                ->maxSize(5120),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private static function submit(?Asset $asset, array $data, Action $action): void
    {
        $employee = auth()->user()->employee;

        try {
            if ($employee === null || $asset === null) {
                throw new LogicException(__('Hanya aset yang sedang Anda pegang yang bisa dilaporkan.'));
            }

            Maintenance::report($employee, $asset, $data['keluhan'], $data['foto'] ?? null);
        } catch (LogicException $exception) {
            // Foto sudah terunggah sebelum laporan ditolak; jangan ditinggal di disk.
            if (filled($data['foto'] ?? null)) {
                Storage::disk(Maintenance::FOTO_DISK)->delete($data['foto']);
            }

            Notification::make()->danger()->title(__('Laporan belum terkirim'))->body($exception->getMessage())->send();
            $action->halt();
        }

        Notification::make()->success()->title(__('Laporan terkirim'))->body(__('Admin akan mengecek laporan Anda.'))->send();
    }

    /**
     * @return Collection<int, Asset>
     */
    private static function heldAssets(): Collection
    {
        $employee = auth()->user()->employee;

        return Asset::withoutGlobalScope(ManagedCategoryScope::class)
            ->where('assigned_type', (new Employee)->getMorphClass())
            ->where('assigned_id', $employee?->getKey() ?? 0)
            ->whereRelation('status', 'nama', Status::DIPAKAI)
            ->orderBy('kode')
            ->get();
    }
}
