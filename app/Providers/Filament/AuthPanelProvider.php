<?php

namespace App\Providers\Filament;

use App\Filament\Pages\Auth\Login;
use App\Filament\Pages\Auth\RequestPasswordReset;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\View\PanelsRenderHook;
use Illuminate\Contracts\View\View;

// Pintu masuk bersama di /login untuk semua role; setelah masuk, user diarahkan ke panel sesuai role-nya.
class AuthPanelProvider extends PanelProvider
{
    use ConfiguresPanel;

    public function panel(Panel $panel): Panel
    {
        return $this->basePanel($panel)
            ->id('auth')
            ->path('')
            ->login(Login::class)
            // Reset memakai kode OTP di halaman permintaan; halaman reset lewat tautan bawaan Filament tidak dipakai.
            ->passwordReset(RequestPasswordReset::class, resetAction: null)
            ->renderHook(PanelsRenderHook::AUTH_LOGIN_FORM_AFTER, fn (): View => view('filament.karyawan.login-guide-link'));
    }
}
