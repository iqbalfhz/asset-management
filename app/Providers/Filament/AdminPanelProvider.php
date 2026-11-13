<?php

namespace App\Providers\Filament;

use App\Http\Controllers\DownloadAttachmentController;
use App\Http\Controllers\DownloadAuditReportController;
use App\Http\Controllers\DownloadBastController;
use App\Http\Controllers\DownloadDisposalReportController;
use App\Http\Controllers\PrintAssetLabelsController;
use App\Http\Controllers\ScanAssetController;
use App\Http\Controllers\ShowMaintenancePhotoController;
use App\Livewire\PersonalInfo;
use BezhanSalleh\FilamentShield\FilamentShieldPlugin;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\FontProviders\LocalFontProvider;
use Filament\Forms\Components\FileUpload;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Icons\Heroicon;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Jeffgreco13\FilamentBreezy\BreezyCore;
use ShuvroRoy\FilamentSpatieLaravelBackup\FilamentSpatieLaravelBackupPlugin;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->viteTheme('resources/css/filament/admin/theme.css')
            // Halaman masuk bersama ada di /login (panel auth).
            ->login(fn (): RedirectResponse => redirect()->route('filament.auth.auth.login'))
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
                Route::get('lampiran/{attachment}', DownloadAttachmentController::class)->name('attachments.download');
                Route::get('bast/{assignment}', [DownloadBastController::class, 'admin'])->whereNumber('assignment')->name('bast.download');
                Route::get('perbaikan/{maintenance}/foto', ShowMaintenancePhotoController::class)->whereNumber('maintenance')->name('maintenances.photo');
                Route::get('penghapusan/{disposal}/berita-acara', DownloadDisposalReportController::class)->whereNumber('disposal')->name('disposals.report');
                Route::get('scan/{kode}', [ScanAssetController::class, 'admin'])->where('kode', '[A-Za-z0-9-]+')->name('assets.scan');
                Route::get('label', PrintAssetLabelsController::class)->name('labels');
                Route::get('stock-opname/{audit}/laporan', DownloadAuditReportController::class)->whereNumber('audit')->name('audits.report');
            })
            ->colors([
                // Abu-abu hangat (sedikit hijau zaitun), dipakai untuk latar, teks, garis.
                'gray' => [
                    50 => 'oklch(0.972 0.005 110)',
                    100 => 'oklch(0.945 0.007 110)',
                    200 => 'oklch(0.905 0.008 110)',
                    300 => 'oklch(0.835 0.009 110)',
                    400 => 'oklch(0.69 0.011 110)',
                    500 => 'oklch(0.555 0.012 110)',
                    600 => 'oklch(0.455 0.012 110)',
                    700 => 'oklch(0.375 0.011 110)',
                    800 => 'oklch(0.285 0.009 110)',
                    900 => 'oklch(0.225 0.008 110)',
                    950 => 'oklch(0.17 0.007 110)',
                ],
                // Merah bata: lebih tenang dari merah default, tetap jelas untuk aksi berbahaya.
                'danger' => '#b4432f',
                // Hijau pinus. Ganti dengan warna brand: 'primary' => '#kodehex'
                'primary' => [
                    50 => 'oklch(0.97 0.015 165)',
                    100 => 'oklch(0.94 0.03 165)',
                    200 => 'oklch(0.88 0.05 165)',
                    300 => 'oklch(0.79 0.07 165)',
                    400 => 'oklch(0.68 0.085 165)',
                    500 => 'oklch(0.57 0.09 165)',
                    600 => 'oklch(0.47 0.08 165)',
                    700 => 'oklch(0.40 0.07 165)',
                    800 => 'oklch(0.34 0.058 165)',
                    900 => 'oklch(0.29 0.045 165)',
                    950 => 'oklch(0.21 0.035 165)',
                ],
            ])
            // Font dimuat dari theme.css (bundle lokal), jadi provider-nya Local tanpa URL.
            ->font('Plus Jakarta Sans Variable', provider: LocalFontProvider::class)
            ->serifFont('Newsreader Variable', provider: LocalFontProvider::class)
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            // Dashboard hanya berisi widget aplikasi (App\Filament\Widgets), tanpa widget bawaan Filament.
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->plugins([
                FilamentShieldPlugin::make(),
                BreezyCore::make()
                    ->myProfile(hasAvatars: true, slug: 'profil')
                    // Foto profil di disk privat, tanpa SVG (SVG bisa membawa skrip saat tautannya dibuka).
                    ->avatarUploadComponent(fn (FileUpload $fileUpload): FileUpload => $fileUpload->disk('local')->visibility('private')->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp']))
                    ->myProfileComponents(['personal_info' => PersonalInfo::class])
                    // 2FA opsional: user mengaktifkannya sendiri dari Profil.
                    ->enableTwoFactorAuthentication(scopeToPanel: false)
                    ->enableBrowserSessions()
                    ->enableSanctumTokens(),
                FilamentSpatieLaravelBackupPlugin::make()
                    ->authorize(fn (): bool => auth()->user()?->hasRole('super_admin') ?? false)
                    ->navigationGroup(fn (): string => __('Administrasi'))
                    ->navigationLabel(fn (): string => __('Backup'))
                    ->navigationSort(97),
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
