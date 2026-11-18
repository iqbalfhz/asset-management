<?php

namespace App\Notifications\Channels;

use App\Notifications\Notice;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Http;
use RuntimeException;

// Pesan ke grup admin lewat Bot API Telegram; token dan ID grup dari .env (TELEGRAM_BOT_TOKEN, TELEGRAM_CHAT_ID).
class TelegramChannel
{
    public static function isConfigured(): bool
    {
        return filled(config('services.telegram.bot_token')) && filled(config('services.telegram.chat_id'));
    }

    public static function adminGroup(): AnonymousNotifiable
    {
        return (new AnonymousNotifiable)->route('telegram', config('services.telegram.chat_id'));
    }

    public function send(object $notifiable, Notice $notice): void
    {
        try {
            Http::timeout(10)
                ->post('https://api.telegram.org/bot'.config('services.telegram.bot_token').'/sendMessage', [
                    'chat_id' => $notifiable->routeNotificationFor('telegram'),
                    'text' => $notice->toTelegram(),
                    'parse_mode' => 'HTML',
                    'link_preview_options' => ['is_disabled' => true],
                ])
                ->throw();
        } catch (ConnectionException|RequestException $exception) {
            // Pesan dan jejak aslinya memuat URL beserta token bot, jadi yang diteruskan hanya alasannya.
            throw new RuntimeException(__('Telegram gagal: :alasan', [
                'alasan' => $exception instanceof RequestException
                    ? $exception->response->json('description', (string) $exception->response->status())
                    : __('server Telegram tidak bisa dihubungi'),
            ]));
        }
    }
}
