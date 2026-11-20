<?php

namespace App\Providers\Filament;

use App\Filament\Pages\Dashboard;
use App\Http\Controllers\DownloadAssetReportController;
use App\Http\Controllers\DownloadAttachmentController;
use App\Http\Controllers\DownloadAuditReportController;
use App\Http\Controllers\DownloadBastController;
use App\Http\Controllers\DownloadDisposalReportController;
use App\Http\Controllers\PrintAssetLabelsController;
use App\Http\Controllers\ScanAssetController;
use App\Http\Controllers\ShowMaintenancePhotoController;
use App\Http\Middleware\LogDownload;
use App\Http\Middleware\UseIndonesianForDocuments;
use App\Livewire\ApiTokens;
use App\Models\Assignment;
use App\Models\Attachment;
use App\Models\Audit;
use App\Models\Disposal;
use App\Models\Maintenance;
use BezhanSalleh\FilamentShield\FilamentShieldPlugin;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Route;
use ShuvroRoy\FilamentSpatieLaravelBackup\FilamentSpatieLaravelBackupPlugin;

class AdminPanelProvider extends PanelProvider
{
    use ConfiguresPanel;

    public function panel(Panel $panel): Panel
    {
        return $this->basePanel($panel)
            ->default()
            ->id('admin')
            ->path('admin')
            ->login($this->sharedLogin())
            // Admin juga memegang aset; pindah ke panel karyawan untuk melihat aset sendiri.
            ->userMenuItems([
                Action::make('karyawanPanel')
                    ->label(fn (): string => __('Panel karyawan'))
                    ->icon(Heroicon::OutlinedArrowsRightLeft)
                    ->url(fn (): string => Filament::getPanel('karyawan')->getUrl()),
            ])
            ->databaseNotifications()
            // Route di dalam panel ikut login, 2FA, dan akses panel.
            ->authenticatedRoutes(function (): void {
                Route::get('lampiran/{attachment}', DownloadAttachmentController::class)->middleware(LogDownload::of('Lampiran', Attachment::class))->name('attachments.download');
                Route::get('bast/{assignment}', [DownloadBastController::class, 'admin'])->whereNumber('assignment')->middleware([UseIndonesianForDocuments::class, LogDownload::of('BAST', Assignment::class)])->name('bast.download');
                Route::get('perbaikan/{maintenance}/foto', ShowMaintenancePhotoController::class)->whereNumber('maintenance')->middleware(LogDownload::of('Foto kerusakan', Maintenance::class))->name('maintenances.photo');
                Route::get('penghapusan/{disposal}/berita-acara', DownloadDisposalReportController::class)->whereNumber('disposal')->middleware([UseIndonesianForDocuments::class, LogDownload::of('Berita acara penghapusan', Disposal::class)])->name('disposals.report');
                Route::get('scan/{kode}', [ScanAssetController::class, 'admin'])->where('kode', '[A-Za-z0-9-]+')->name('assets.scan');
                Route::get('label', PrintAssetLabelsController::class)->middleware(UseIndonesianForDocuments::class)->name('labels');
                Route::get('stock-opname/{audit}/laporan', DownloadAuditReportController::class)->whereNumber('audit')->middleware([UseIndonesianForDocuments::class, LogDownload::of('Laporan stock opname', Audit::class)])->name('audits.report');
                Route::get('laporan/unduh', DownloadAssetReportController::class)->middleware([UseIndonesianForDocuments::class, LogDownload::of('Laporan aset')])->name('reports.assets');
            })
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            // Dashboard hanya berisi widget aplikasi (App\Filament\Widgets), tanpa widget bawaan Filament.
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->plugins([
                FilamentShieldPlugin::make(),
                // Token REST API (/api/v1) dibuat sendiri dari Profil; izinnya tetap mengikuti role pemiliknya.
                $this->profilePlugin()
                    ->enableSanctumTokens(permissions: fn (): array => ['baca-aset' => __('Baca aset'), 'serah-terima' => __('Serah-terima')])
                    ->myProfileComponents(['sanctum_tokens' => ApiTokens::class]),
                FilamentSpatieLaravelBackupPlugin::make()
                    ->authorize(fn (): bool => auth()->user()?->hasRole('super_admin') ?? false)
                    ->navigationGroup(fn (): string => __('Administrasi'))
                    ->navigationLabel(fn (): string => __('Backup'))
                    ->navigationSort(97),
            ]);
    }
}
