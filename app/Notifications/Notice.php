<?php

namespace App\Notifications;

use App\Enums\AssetRequestStatus;
use App\Filament\Karyawan\Pages\MyAssetRequests;
use App\Filament\Resources\AssetRequests\AssetRequestResource;
use App\Filament\Resources\Maintenances\MaintenanceResource;
use App\Models\Asset;
use App\Models\AssetRequest;
use App\Models\Category;
use App\Models\Employee;
use App\Models\Setting;
use App\Models\User;
use App\Notifications\Channels\TelegramChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

// Notifikasi bertema Kertas: pembuka, beberapa daftar, dan satu tombol. Isinya disusun saat dikirim,
// jadi tidak dibaca ulang dengan hak lihat user lain. Ke user lewat email, ke grup admin lewat Telegram.
final class Notice extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  array<string, array<int, string>>  $sections  judul daftar => barisnya
     */
    public function __construct(
        public string $subject,
        public string $intro,
        public array $sections,
        public string $actionLabel,
        public string $actionUrl,
    ) {}

    public static function damageReported(Asset $asset, Employee $reporter, string $keluhan): self
    {
        return new self(
            __('Laporan kerusakan baru: :kode', ['kode' => $asset->kode]),
            __(':nama melaporkan kerusakan pada :aset.', ['nama' => $reporter->nama, 'aset' => "{$asset->kode} — {$asset->nama}"]),
            [__('Keluhan') => [$keluhan]],
            __('Buka daftar perbaikan'),
            MaintenanceResource::getUrl(panel: 'admin'),
        );
    }

    public static function assetRequested(Employee $employee, Category $category, string $alasan): self
    {
        return new self(
            __('Permintaan aset baru: :kategori', ['kategori' => $category->nama]),
            __(':nama meminta aset :kategori.', ['nama' => $employee->nama, 'kategori' => $category->nama]),
            [__('Alasan') => [$alasan]],
            __('Buka permintaan aset'),
            AssetRequestResource::getUrl(panel: 'admin'),
        );
    }

    public static function assetRequestDecided(AssetRequest $request): self
    {
        $kategori = $request->category->nama;
        $approved = $request->status === AssetRequestStatus::Disetujui;

        return new self(
            $approved ? __('Permintaan :kategori disetujui', ['kategori' => $kategori]) : __('Permintaan :kategori ditolak', ['kategori' => $kategori]),
            $approved
                ? __('Permintaan aset :kategori Anda disetujui. Setelah admin menyerahkan asetnya, tekan Terima di menu Aset saya.', ['kategori' => $kategori])
                : __('Permintaan aset :kategori Anda ditolak.', ['kategori' => $kategori]),
            filled($request->catatan) ? [__('Catatan admin') => [$request->catatan]] : [],
            __('Buka Permintaan saya'),
            MyAssetRequests::getUrl(panel: 'karyawan'),
        );
    }

    /**
     * @param  array<string, array<int, string>>  $sections
     */
    public static function dailyDigest(array $sections, string $url): self
    {
        return new self(
            __('Ringkasan harian :tanggal', ['tanggal' => today()->translatedFormat('j F Y')]),
            __('Berikut hal yang perlu ditindaklanjuti.'),
            $sections,
            __('Buka :app', ['app' => config('app.name')]),
            $url,
        );
    }

    public static function telegramTest(): self
    {
        return new self(
            __('Uji notifikasi Telegram'),
            __('Bot :app sudah terhubung ke grup ini. Laporan kerusakan dan permintaan aset baru akan dikirim ke sini.', ['app' => config('app.name')]),
            [],
            __('Buka :app', ['app' => config('app.name')]),
            url('/'),
        );
    }

    /**
     * Email ke pengelola kategori, dan ke grup admin Telegram kalau dinyalakan di Pengaturan.
     */
    public function sendToManagers(int $roleId, int $companyId): void
    {
        User::managersOf($roleId, $companyId)->each->notify($this);

        if (Setting::get('telegram_enabled', false) && TelegramChannel::isConfigured()) {
            TelegramChannel::adminGroup()->notify($this);
        }
    }

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        if ($notifiable instanceof AnonymousNotifiable) {
            return [TelegramChannel::class];
        }

        return Setting::get('email_notifikasi', true) ? ['mail'] : [];
    }

    /**
     * Dikirim setelah respons selesai, tanpa worker, seperti email kode OTP.
     *
     * @return array<string, string>
     */
    public function viaConnections(): array
    {
        return ['mail' => 'deferred', TelegramChannel::class => 'deferred'];
    }

    /**
     * Teks HTML Telegram; semua isian di-escape.
     */
    public function toTelegram(): string
    {
        $sections = collect($this->sections)->map(fn (array $lines, string $heading): string => '<b>'.e($heading)."</b>\n"
            .collect($lines)->map(fn (string $line): string => '• '.e(Str::limit($line, 500)))->implode("\n"));

        return collect(['<b>'.e($this->subject).'</b>', e($this->intro), ...$sections, '<a href="'.e($this->actionUrl).'">'.e($this->actionLabel).'</a>'])
            ->implode("\n\n");
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject($this->subject)
            ->view(['html' => 'mail.notice', 'text' => 'mail.notice-text'], [
                'name' => $notifiable->name ?? null,
                'notice' => $this,
            ]);
    }
}
