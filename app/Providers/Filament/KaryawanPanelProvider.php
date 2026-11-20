<?php

namespace App\Providers\Filament;

use App\Http\Controllers\DownloadBastController;
use App\Http\Middleware\LogDownload;
use App\Http\Middleware\UseIndonesianForDocuments;
use App\Models\Assignment;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Route;

class KaryawanPanelProvider extends PanelProvider
{
    use ConfiguresPanel;

    public function panel(Panel $panel): Panel
    {
        return $this->basePanel($panel)
            ->id('karyawan')
            ->path('karyawan')
            ->login($this->sharedLogin())
            ->userMenuItems([
                $this->languageMenuItem(),
                Action::make('adminPanel')
                    ->label(fn (): string => __('Panel admin'))
                    ->icon(Heroicon::OutlinedArrowsRightLeft)
                    ->url(fn (): string => Filament::getPanel('admin')->getUrl())
                    ->visible(fn (): bool => auth()->user()->canAccessPanel(Filament::getPanel('admin'))),
            ])
            ->authenticatedRoutes(function (): void {
                Route::get('bast/{assignment}', [DownloadBastController::class, 'employee'])->whereNumber('assignment')->middleware([UseIndonesianForDocuments::class, LogDownload::of('BAST', Assignment::class)])->name('bast.download');
            })
            // Tanpa dashboard: halaman awal adalah menu pertama (Aset saya).
            ->discoverPages(in: app_path('Filament/Karyawan/Pages'), for: 'App\Filament\Karyawan\Pages')
            ->plugins([
                $this->profilePlugin(),
            ]);
    }
}
