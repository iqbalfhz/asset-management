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
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

// Email notifikasi bertema Kertas: pembuka, beberapa daftar, dan satu tombol. Isinya disusun saat dikirim,
// jadi tidak dibaca ulang dengan hak lihat user lain.
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

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return Setting::get('email_notifikasi', true) ? ['mail'] : [];
    }

    /**
     * Dikirim setelah respons selesai, tanpa worker, seperti email kode OTP.
     *
     * @return array<string, string>
     */
    public function viaConnections(): array
    {
        return ['mail' => 'deferred'];
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
