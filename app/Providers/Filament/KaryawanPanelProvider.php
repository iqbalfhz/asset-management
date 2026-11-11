<?php

namespace App\Providers\Filament;

use App\Filament\Pages\Auth\Login;
use App\Http\Controllers\DownloadBastController;
use App\Livewire\PersonalInfo;
use Filament\FontProviders\LocalFontProvider;
use Filament\Forms\Components\FileUpload;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\View\PanelsRenderHook;
use Illuminate\Contracts\View\View;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Jeffgreco13\FilamentBreezy\BreezyCore;

class KaryawanPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('karyawan')
            ->path('karyawan')
            ->viteTheme('resources/css/filament/admin/theme.css')
            ->login(Login::class)
            ->passwordReset()
            ->authenticatedRoutes(function (): void {
                Route::get('bast/{assignment}', [DownloadBastController::class, 'employee'])->whereNumber('assignment')->name('bast.download');
            })
            ->renderHook(PanelsRenderHook::AUTH_LOGIN_FORM_AFTER, fn (): View => view('filament.karyawan.login-guide-link'))
            ->plugins([
                BreezyCore::make()
                    ->myProfile(hasAvatars: true, slug: 'profil')
                    // Foto profil disimpan di disk privat, bukan publik seperti bawaan Breezy.
                    ->avatarUploadComponent(fn (FileUpload $fileUpload): FileUpload => $fileUpload->disk('local')->visibility('private'))
                    ->myProfileComponents(['personal_info' => PersonalInfo::class])
                    ->enableTwoFactorAuthentication(force: true, scopeToPanel: false)
                    ->enableBrowserSessions(),
            ])
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
            ->discoverResources(in: app_path('Filament/Karyawan/Resources'), for: 'App\Filament\Karyawan\Resources')
            // Tanpa dashboard: halaman awal adalah menu pertama (Aset saya).
            ->discoverPages(in: app_path('Filament/Karyawan/Pages'), for: 'App\Filament\Karyawan\Pages')
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
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
