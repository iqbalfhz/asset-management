<?php

namespace App\Filament\Pages\Auth;

use Filament\Auth\Pages\PasswordReset\RequestPasswordReset as BaseRequestPasswordReset;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Password;

class RequestPasswordReset extends BaseRequestPasswordReset
{
    // Email tak terdaftar atau baru saja diminta dijawab sama seperti berhasil, supaya email pengguna tidak bisa ditebak.
    protected function getFailureNotification(string $status): ?Notification
    {
        $this->form->fill();

        return $this->getSentNotification(Password::RESET_LINK_SENT);
    }
}
