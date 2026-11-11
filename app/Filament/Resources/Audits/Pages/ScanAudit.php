<?php

namespace App\Filament\Resources\Audits\Pages;

use App\Enums\AuditResult;
use App\Enums\AuditStatus;
use App\Filament\Resources\Audits\AuditResource;
use App\Models\Audit;
use App\Models\AuditItem;
use Filament\Actions\Action;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Locked;
use LogicException;

/**
 * @property-read Audit $record
 */
class ScanAudit extends Page
{
    use InteractsWithRecord;

    protected static string $resource = AuditResource::class;

    protected string $view = 'filament.audits.scan';

    public ?string $code = null;

    /**
     * Hasil scan terakhir yang ditampilkan besar di layar; hanya diisi server.
     *
     * @var array{tone: string, title: string, body: string}|null
     */
    #[Locked]
    public ?array $lastResult = null;

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);

        abort_unless(auth()->user()->can('update', $this->record), 403);

        if ($this->record->status !== AuditStatus::Berjalan) {
            $this->redirect(AuditResource::getUrl('view', ['record' => $this->record]));
        }
    }

    public function getTitle(): string|Htmlable
    {
        return __('Scan · :ruang', ['ruang' => $this->record->room->nama]);
    }

    /**
     * Dipanggil oleh kamera dan oleh kolom kode (scanner barcode atau ketikan).
     */
    public function scan(string $scanned): void
    {
        abort_unless(auth()->user()->can('update', $this->record), 403);

        if (blank($scanned) || mb_strlen($scanned) > 255) {
            $this->lastResult = ['tone' => 'danger', 'title' => __('Tidak tercatat'), 'body' => __('Isi scan tidak dikenali.')];

            return;
        }

        try {
            $item = $this->record->scan($scanned)->load(['asset', 'recordedRoom']);
        } catch (LogicException $exception) {
            $this->lastResult = ['tone' => 'danger', 'title' => __('Tidak tercatat'), 'body' => $exception->getMessage()];

            return;
        }

        $asset = "{$item->asset->kode} · {$item->asset->nama}";

        $this->lastResult = match (true) {
            ! $item->wasRecentlyCreated && ! $item->wasChanged() => ['tone' => 'gray', 'title' => __('Sudah discan'), 'body' => "{$asset} · {$item->hasil->getLabel()}"],
            $item->hasil === AuditResult::SalahTempat => ['tone' => 'warning', 'title' => __('Salah tempat'), 'body' => $asset.' · '.__('tercatat di :ruang', ['ruang' => $item->recordedRoom?->nama ?? __('belum ada lokasi')])],
            default => ['tone' => 'success', 'title' => __('Ditemukan'), 'body' => $asset],
        };
    }

    public function submitCode(): void
    {
        $this->scan((string) $this->code);
        $this->code = null;
    }

    /**
     * @return array<string, int>
     */
    public function counts(): array
    {
        return $this->record->resultCounts();
    }

    /**
     * @return Collection<int, AuditItem>
     */
    public function recentItems(): Collection
    {
        return $this->record->items()
            ->with('asset')
            ->whereNotNull('discan_pada')
            ->latest('discan_pada')
            ->latest('id')
            ->limit(8)
            ->get();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('done')
                ->label(__('Selesai scan'))
                ->icon(Heroicon::OutlinedCheck)
                ->color('gray')
                ->url(AuditResource::getUrl('view', ['record' => $this->record])),
        ];
    }
}
