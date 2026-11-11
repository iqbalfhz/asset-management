<?php

namespace App\Filament\Karyawan\Pages;

use App\Filament\Karyawan\Actions\ReportDamageAction;
use App\Models\Asset;
use App\Models\Employee;
use App\Models\Scopes\ManagedCategoryScope;
use App\Models\Status;
use Filament\Actions\Action;
use Filament\Infolists\Components\TextEntry;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Colors\Color;
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
                Section::make()
                    ->columns(2)
                    ->schema([
                        TextEntry::make('kode'),
                        TextEntry::make('nama'),
                        TextEntry::make('category.nama')
                            ->label(__('Kategori')),
                        TextEntry::make('status.nama')
                            ->label(__('Status'))
                            ->badge()
                            ->color(fn (Asset $record): ?array => $record->status->warna ? Color::hex($record->status->warna) : null),
                        TextEntry::make('model')
                            ->label(__('Model'))
                            ->state(fn (Asset $record): ?string => $record->assetModel ? "{$record->assetModel->manufacturer->nama} {$record->assetModel->nama}" : null)
                            ->placeholder('—'),
                        TextEntry::make('no_seri')
                            ->label(__('Nomor seri'))
                            ->placeholder('—'),
                    ]),
            ]);
    }

    private function asset(): Asset
    {
        $employee = auth()->user()->employee;

        return Asset::withoutGlobalScope(ManagedCategoryScope::class)
            ->with(['category', 'status', 'assetModel.manufacturer'])
            ->where('kode', $this->kode)
            ->where('assigned_type', (new Employee)->getMorphClass())
            ->where('assigned_id', $employee?->getKey() ?? 0)
            ->whereHas('status', fn (Builder $query) => $query->whereIn('nama', [Status::DIPAKAI, Status::DALAM_PERBAIKAN]))
            ->firstOrFail();
    }
}
