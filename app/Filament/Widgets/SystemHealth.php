<?php

namespace App\Filament\Widgets;

use App\Enums\DisposalStatus;
use App\Filament\Resources\Disposals\DisposalResource;
use App\Models\Disposal;
use App\Models\ErrorLog;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Notifications\Notification;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Widgets\Widget;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Backup\BackupDestination\BackupDestination;

// Hal yang gagal diam-diam (tidak ada orang lain yang memberi tahu) dan bisa ditindak Super Admin.
class SystemHealth extends Widget implements HasActions, HasSchemas
{
    use InteractsWithActions;
    use InteractsWithSchemas;

    public const HEARTBEAT = 'scheduler:detak';

    protected static ?int $sort = 0;

    protected int|string|array $columnSpan = 'full';

    protected string $view = 'filament.widgets.system-health';

    public static function canView(): bool
    {
        return auth()->user()?->hasRole('super_admin') ?? false;
    }

    /**
     * @return array<string, array{label: string, value: string, ok: bool, detail?: ?string, url?: string}>
     */
    public function checks(): array
    {
        return [
            'backup' => $this->backup(),
            'scheduler' => $this->scheduler(),
            'jobs' => $this->failedJobs(),
            'errors' => $this->errors(),
            'disposals' => $this->disposals(),
        ];
    }

    public function retryFailedJobsAction(): Action
    {
        return Action::make('retryFailedJobs')
            ->label(__('Kirim ulang semua'))
            ->size('sm')
            ->requiresConfirmation()
            ->modalDescription(__('Perbaiki dulu penyebabnya, misalnya kunci API email. Job yang gagal dimasukkan lagi ke antrean dan diproses dalam satu menit.'))
            ->visible(fn (): bool => static::canView() && DB::table('failed_jobs')->exists())
            ->action(function (): void {
                Artisan::call('queue:retry', ['id' => ['all']]);

                Notification::make()->success()->title(__('Job dimasukkan lagi ke antrean'))->send();
            });
    }

    /**
     * @return array{label: string, value: string, ok: bool, detail: ?string}
     */
    private function backup(): array
    {
        $monitored = config('backup.monitor_backups.0');
        $newest = BackupDestination::create($monitored['disks'][0], $monitored['name'])->newestBackup()?->date();
        // Backup jalan tiap malam; dua jam kelonggaran supaya tidak merah selama backup berikutnya berjalan.
        $ok = $newest !== null && $newest->greaterThan(now()->subHours(26));

        return [
            'label' => __('Backup terakhir'),
            'value' => $newest?->timezone(config('app.timezone'))->translatedFormat('j M Y H:i') ?? __('Belum ada'),
            'ok' => $ok,
            'detail' => $ok ? null : __('Backup tiap malam tidak berjalan. Cek email backup gagal dan tugas terjadwal.'),
        ];
    }

    /**
     * @return array{label: string, value: string, ok: bool, detail: ?string}
     */
    private function scheduler(): array
    {
        $beat = Cache::get(self::HEARTBEAT);
        $last = $beat === null ? null : Carbon::createFromTimestamp($beat, config('app.timezone'));
        $ok = $last !== null && $last->greaterThan(now()->subMinutes(5));

        return [
            'label' => __('Tugas terjadwal'),
            'value' => $last?->diffForHumans() ?? __('Belum pernah jalan'),
            'ok' => $ok,
            'detail' => $ok ? null : __('Email, backup, dan pengingat tertahan. Cek Scheduled Task di Coolify.'),
        ];
    }

    /**
     * @return array{label: string, value: string, ok: bool, detail: ?string}
     */
    private function failedJobs(): array
    {
        $count = DB::table('failed_jobs')->count();
        $latest = $count > 0 ? DB::table('failed_jobs')->latest('failed_at')->value('exception') : null;

        return [
            'label' => __('Job gagal'),
            'value' => (string) $count,
            'ok' => $count === 0,
            'detail' => $latest === null ? null : Str::limit(Str::before($latest, "\n"), 300),
        ];
    }

    /**
     * @return array{label: string, value: string, ok: bool, detail: ?string}
     */
    private function errors(): array
    {
        $recent = ErrorLog::query()->where('updated_at', '>=', now()->subDay());
        $latest = (clone $recent)->latest('updated_at')->first();

        return [
            'label' => __('Error 24 jam'),
            'value' => (string) $recent->count(),
            'ok' => $latest === null,
            'detail' => $latest === null ? null : "{$latest->pesan} — {$latest->lokasi} ({$latest->jumlah}×)",
        ];
    }

    /**
     * @return array{label: string, value: string, ok: bool, url: string}
     */
    private function disposals(): array
    {
        $count = Disposal::query()->where('status', DisposalStatus::Diajukan)->count();

        return [
            'label' => __('Penghapusan yang menunggu persetujuan'),
            'value' => (string) $count,
            'ok' => $count === 0,
            'url' => DisposalResource::getUrl(),
        ];
    }
}
