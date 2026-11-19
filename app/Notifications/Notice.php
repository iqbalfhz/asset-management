<?php

namespace App\Notifications;

use App\Enums\AssetRequestStatus;
use App\Enums\DigestFrequency;
use App\Filament\Karyawan\Pages\MyAssetRequests;
use App\Filament\Resources\AssetRequests\AssetRequestResource;
use App\Filament\Resources\Assets\AssetResource;
use App\Filament\Resources\Maintenances\MaintenanceResource;
use App\Models\Asset;
use App\Models\AssetReminder;
use App\Models\AssetRequest;
use App\Models\Assignment;
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

    public static function reminderDue(AssetReminder $reminder, Asset $asset): self
    {
        $tanggal = $reminder->tanggal->translatedFormat('j F Y');

        return new self(
            __(':jenis: :kode, :tanggal', ['jenis' => $reminder->jenis, 'kode' => $asset->kode, 'tanggal' => $tanggal]),
            __(':jenis untuk :aset jatuh tempo :tanggal, :hari hari lagi.', [
                'jenis' => $reminder->jenis,
                'aset' => Assignment::labelFor($asset),
                'tanggal' => $tanggal,
                'hari' => (int) today()->diffInDays($reminder->tanggal),
            ]),
            filled($reminder->catatan) ? [__('Catatan') => [$reminder->catatan]] : [],
            __('Buka aset'),
            AssetResource::getUrl('view', ['record' => $asset], panel: 'admin'),
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
    public static function digest(DigestFrequency $frequency, array $sections, string $url): self
    {
        return new self(
            match ($frequency) {
                DigestFrequency::Bulanan => __('Ringkasan bulanan :bulan', ['bulan' => today()->translatedFormat('F Y')]),
                DigestFrequency::Tahunan => __('Ringkasan tahunan :tahun', ['tahun' => today()->year]),
                default => __('Ringkasan mingguan :tanggal', ['tanggal' => today()->translatedFormat('j F Y')]),
            },
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
