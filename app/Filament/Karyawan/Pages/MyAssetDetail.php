<?php

namespace App\Filament\Karyawan\Pages;

use App\Filament\Karyawan\Actions\ReportDamageAction;
use App\Filament\Resources\Assets\Schemas\AssetInfolist;
use App\Models\Asset;
use App\Models\Employee;
use App\Models\Scopes\ManagedCategoryScope;
use App\Models\Status;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;

// Tujuan scan QR untuk karyawan: hanya aset yang sedang dipegangnya.
class MyAssetDetail extends Page
{
    protected static ?string $slug = 'aset/{kode}';

    protected static bool $shouldRegisterNavigation = false;

    // Hanya kode yang disimpan di state Livewire; aset dibaca ulang dengan batasan pemegang setiap request.
    public string $kode;

    public function mount(string $kode): void
    {
        $this->kode = $kode;
        $this->asset();
    }

    public function getTitle(): string
    {
        return $this->asset()->kode;
    }

    protected function getHeaderActions(): array
    {
        return [
            ReportDamageAction::forAsset()->record($this->asset()),
            Action::make('myAssets')
                ->label(__('Semua aset saya'))
                ->icon(Heroicon::OutlinedComputerDesktop)
                ->color('gray')
                ->url(MyAssets::getUrl()),
        ];
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->record($this->asset())
            ->components([
                AssetInfolist::identity(),
            ]);
    }

    // Dibaca sekali per request: judul, tombol, dan isi halaman memakai aset yang sama.
    private function asset(): Asset
    {
        return once(fn (): Asset => Asset::withoutGlobalScope(ManagedCategoryScope::class)
            ->with(['category', 'status', 'assetModel.manufacturer'])
            ->where('kode', $this->kode)
            ->where('assigned_type', (new Employee)->getMorphClass())
            ->where('assigned_id', auth()->user()->employee?->getKey() ?? 0)
            ->whereHas('status', fn (Builder $query) => $query->whereIn('nama', [Status::DIPAKAI, Status::DALAM_PERBAIKAN]))
            ->firstOrFail());
    }
}
