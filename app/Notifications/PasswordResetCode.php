<?php

namespace App\Notifications;

use App\Filament\Pages\Auth\RequestPasswordReset;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use SensitiveParameter;

class PasswordResetCode extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(#[SensitiveParameter] public string $code)
    {
        // Dikirim oleh request itu sendiri setelah respons selesai: cepat tanpa worker, dan lama respons tidak membocorkan email terdaftar.
        $this->onConnection('deferred');
    }

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('Kode atur ulang kata sandi'))
            ->view(['html' => 'mail.password-reset-code', 'text' => 'mail.password-reset-code-text'], [
                'code' => $this->code,
                'name' => $notifiable->name ?? null,
                'minutes' => RequestPasswordReset::CODE_MINUTES,
                'appName' => config('app.name'),
            ]);
    }
}
