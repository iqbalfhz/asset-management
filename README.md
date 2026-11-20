# Asset Management

Aplikasi manajemen aset internal berbasis **Laravel + Filament**, dirancang untuk perusahaan yang ingin mendata aset, mencatat serah-terima resmi (BAST) dengan tanda tangan, serta mengelola perawatan, lisensi, dan perlengkapan dalam satu sistem.

Antarmuka berbahasa Indonesia dan Inggris (pilihan per pengguna), mendukung multi-perusahaan, dan menerbitkan dokumen resmi (BAST, berita acara, laporan) dalam PDF berbahasa Indonesia.

---

## Fitur

- **Aset** — pendataan dengan kode unik (`PT-KATEGORI-TAHUN-NOMOR`), spesifikasi fleksibel, foto, label QR/barcode, penyusutan & nilai buku.
- **Serah-terima (BAST)** — ke karyawan/ruang/departemen/aset lain, dengan tanda tangan di layar atau unggah foto BAST tercetak, status berubah hanya lewat transaksi.
- **Perawatan & penghapusan** — laporan kerusakan oleh karyawan, perbaikan, dan penghapusan dengan berita acara.
- **Stock opname** — sesi audit per ruang dengan pemindaian QR.
- **Lisensi, barang habis pakai, aksesori, komponen, paket (kits)** — lengkap dengan stok dan riwayat.
- **Permintaan & peminjaman** — karyawan meminta aset atau memesan barang bersama (mis. proyektor) per jam.
- **Pengingat** — tanggal penting aset (pajak, servis, kontrak) dengan notifikasi email.
- **Laporan PDF, kalender, dasbor, log aktivitas & log masuk.**
- **Multi-perusahaan**, hak akses granular (Filament Shield), 2FA opsional, dua bahasa.

## Teknologi

- [Laravel 13](https://laravel.com) · [Filament 5](https://filamentphp.com) (Livewire, Alpine, Tailwind)
- PHP 8.3+ · MySQL/MariaDB (atau SQLite untuk test)
- [Pest](https://pestphp.com) · [Laravel Shield](https://filamentphp.com/plugins/bezhan-shield) · [spatie/laravel-backup](https://spatie.be/docs/laravel-backup) · [spatie/laravel-activitylog](https://spatie.be/docs/laravel-activitylog)
- Produksi: [FrankenPHP](https://frankenphp.dev) via `Dockerfile`

---

## Persyaratan

- PHP **8.3+** dengan ekstensi umum Laravel + `intl` dan `gd`
- Composer, Node.js 20+ & npm
- Database: MySQL 8+/MariaDB (SQLite cukup untuk menjalankan test)

## Instalasi

```bash
git clone https://github.com/iqbalfhz/asset-management.git
cd asset-management

composer install
npm install

cp .env.example .env
php artisan key:generate
# Isi SEED_PASSWORD di .env (min. 12 karakter, huruf & angka) sebelum seeding.

php artisan migrate --seed
php artisan shield:generate --all --panel=admin   # generate izin

npm run build        # atau: npm run dev (saat pengembangan)
php artisan serve    # buka http://localhost:8000
```

Login awal: username **`administrator`**, password = nilai `SEED_PASSWORD`.

---

## Konfigurasi `.env` penting

| Variabel | Keterangan |
|---|---|
| `APP_KEY` | Kunci enkripsi. Data terenkripsi (secret 2FA, product key) bergantung padanya — **jangan ganti di produksi.** |
| `APP_URL` | URL dasar aplikasi; dipakai untuk tautan dan pembatasan host. |
| `SEED_PASSWORD` | Password akun `administrator` saat seeding. |
| `DB_*` | Koneksi database. |
| `MAIL_*` | Pengiriman email (notifikasi, reset password). |
| `BACKUP_ARCHIVE_PASSWORD` | Password enkripsi arsip backup. Simpan terpisah — tanpanya backup tidak bisa dibuka. |

Jangan commit `.env`. Isi rahasia langsung di server/panel deploy.

---

## Penggunaan

| Rute | Untuk |
|---|---|
| `/login` | Halaman masuk semua role |
| `/admin` | Panel pengelola aset |
| `/karyawan` | Panel karyawan (aset saya, lapor kerusakan, terima aset) |

Role bawaan: `super_admin`, `admin_it`, `admin_ga`, `karyawan`. Tiap kategori aset ditangani satu role; admin hanya melihat kategori yang dikelolanya. 2FA diaktifkan sendiri dari Profil.

Setiap kali menambah resource Filament, jalankan ulang:
```bash
php artisan shield:generate --all --panel=admin
```

## Testing

```bash
php artisan test          # seluruh suite (Pest)
vendor/bin/pint           # formatter gaya kode
```

---

## Deployment

Aplikasi berjalan dari satu image **FrankenPHP** (`Dockerfile`) dan bisa di-deploy ke host Docker mana pun (contoh: [Coolify](https://coolify.io)). HTTPS dan domain dapat ditangani oleh reverse proxy atau Cloudflare Tunnel.

Yang perlu disiapkan saat deploy:

- **Scheduler**: jalankan `php artisan schedule:run` tiap menit (cron atau scheduled task). Ini memproses antrean, backup, dan email terjadwal — tanpa perlu container worker terpisah.
- **Migrasi**: `php artisan migrate --force` saat rilis.
- **Backup**: `php artisan backup:run` (dijadwalkan). Restore: unduh arsip → buka dengan `BACKUP_ARCHIVE_PASSWORD` → import `.sql`.

Jadwal bawaan (zona waktu aplikasi): backup harian dini hari, ringkasan & pengingat email pukul 07:00, pembersihan log masuk lama.

---

## Operasional & keamanan

Beberapa praktik yang disarankan bagi siapa pun yang menjalankan aplikasi ini di produksi:

- **Aktifkan 2FA** untuk semua akun admin. Hak admin mencakup data pribadi karyawan, jadi password saja tidak cukup.
- **Simpan `APP_KEY` dan `BACKUP_ARCHIVE_PASSWORD`** di luar server (mis. password manager). Hilang = data/backup tak terpulihkan.
- **Uji pulihkan backup** secara berkala.
- Jaga agar panel kontrol infrastruktur (reverse proxy, dashboard deploy) tidak terekspos publik tanpa autentikasi kuat + 2FA.

### Rem darurat `lockdown`

Saat sistem diduga dibobol, jalankan dari CLI server (di dalam container aplikasi):

```bash
php artisan lockdown            # logout semua sesi + cabut semua token API
php artisan lockdown --accounts # + bekukan semua akun non-super-admin
php artisan lockdown --lift     # aktifkan kembali akun yang tadi dikunci
```

Tidak menghapus data, password, `APP_KEY`, atau backup. Super-admin tidak pernah ikut dibekukan, sehingga pemilik tetap bisa masuk untuk menyelidiki (menu **Log masuk** & **Log aktivitas**).

Urutan tanggap insiden: putus akses → `lockdown` → ganti password & nyalakan 2FA → selidiki → pulihkan dari backup bila perlu. **Jangan ganti `APP_KEY`** (merusak data terenkripsi).

---

## Lisensi

[MIT](https://opensource.org/licenses/MIT).
