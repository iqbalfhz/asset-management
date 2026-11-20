# Asset Management

Aplikasi manajemen aset internal: pendataan aset, serah-terima dengan BAST dan tanda tangan, perbaikan, penghapusan, stock opname (QR), lisensi, barang habis pakai, aksesori, komponen, paket (kits), permintaan aset, peminjaman barang, pengingat tanggal penting, dan laporan PDF. Dua bahasa (Indonesia/English), multi-perusahaan.

> Dokumen ini adalah **contekan operasional**. Rencana lengkap (PRD, ERD, panduan per fase) ada di [dokumen rencana](https://claude.ai/artifact/EeufZa4NcNAVG5zE8Hsej2). Aturan teknis proyek ada di `CLAUDE.md`.

---

## 🚨 Darurat: sistem diduga diretas

Semua dijalankan dari **Terminal Coolify** / SSH server. Cari container aplikasi lewat port 8091:

```bash
CID=$(docker ps --filter publish=8091 -q)

# 1. Kunci cepat: logout semua sesi + cabut semua token API
docker exec $CID php artisan lockdown --force

# 2. Kunci penuh: + bekukan semua akun (hanya super-admin yang masih bisa masuk)
docker exec $CID php artisan lockdown --accounts --force

# 3. Buka kembali setelah aman (hanya akun yang tadi dikunci, bukan yang resign)
docker exec $CID php artisan lockdown --lift
```

`lockdown` **tidak** menghapus data, password, `APP_KEY`, atau backup — semua bisa dikembalikan. Super-admin tidak pernah ikut dibekukan.

**Urutan tanggap darurat:**
1. Cloudflare → **Under Attack Mode** ON. Kalau data disedot aktif, Stop container di Coolify.
2. Jalankan `lockdown` (langkah di atas).
3. Ganti semua password admin, **nyalakan 2FA** untuk semuanya.
4. Selidiki lewat menu **Log masuk** (IP & jam penyerang) dan **Log aktivitas** (data yang berubah).
5. Pulihkan dari backup bersih bila ada data dirusak.
6. **Jangan ganti `APP_KEY`** — data terenkripsi (2FA, product key) jadi tidak terbaca.
7. Tutup lubangnya, lalu nyalakan lagi.

---

## Akses

| | |
|---|---|
| Production | https://asset.iqbalfhz.my.id |
| Login (semua role) | `/login` |
| Panel pengelola aset | `/admin` |
| Panel karyawan | `/karyawan` |
| Login awal | username `administrator`, password dari `SEED_PASSWORD` di `.env` |

Role: `super_admin`, `admin_it`, `admin_ga`, `karyawan`. 2FA opsional, diaktifkan sendiri dari Profil.

---

## Stack

- **Laravel 13** + **Filament 5**, PHP 8.5, tema "Kertas"
- **MySQL** (server 9.7 LTS, laptop 8.4 LTS), **Pest** untuk test
- Deploy: **Coolify** + **FrankenPHP** (`Dockerfile`), HTTPS & domain lewat **Cloudflare Tunnel**
- Image server: `serversideup/php:8.5-frankenphp-trixie-v5.1.5`
- Antrean & jadwal: **Scheduled Task Coolify** (`schedule:run` tiap menit), tanpa container worker
- Backup: **spatie/laravel-backup** ke **Cloudflare R2** (terenkripsi, dikunci 7 hari)

---

## Pengembangan lokal (Herd)

```bash
cp .env.example .env            # isi APP_KEY (php artisan key:generate), SEED_PASSWORD, dll
php artisan migrate --seed      # SEED_PASSWORD wajib diisi dulu
npm install && npm run dev      # aset front-end (atau: npm run build)
```

Herd menyajikan situs otomatis. Setelah menambah resource Filament: `php artisan shield:generate --all --panel=admin`.

---

## Test

```bash
php artisan test               # seluruh suite
php artisan test --compact tests/Feature/NamaTest.php
vendor/bin/pint                # rapikan gaya kode
```

Fitur baru wajib punya Pest test, terutama untuk akses (siapa boleh melihat/mengubah apa).

---

## Deploy

**Push ke `main` = deploy langsung ke production** (Coolify auto-deploy). Ada downtime singkat ~15–30 detik tiap deploy.

Cek runtime server:
```bash
CID=$(docker ps --filter publish=8091 -q)
docker exec $CID php -v                 # patch PHP 8.5
docker exec $CID frankenphp version     # harus >= 1.12.3
```

Cek update dependensi (dari laptop):
```bash
composer outdated --direct              # paket PHP
composer audit                          # celah keamanan
npm outdated
```

Update patch PHP/FrankenPHP di server = naikkan tag image di `Dockerfile` (lihat tag terbaru di Docker Hub: `serversideup/php`), lalu push.

---

## Tugas terjadwal (waktu Asia/Jakarta)

| Jam | Tugas |
|---|---|
| tiap menit | antrean (import/export/backup diproses) |
| 01:00 | `backup:clean` (buang backup lama) |
| 01:30 | `backup:run` (backup ke R2) |
| 02:00 | `model:prune` (log masuk > 1 tahun dihapus) |
| 03:00 | `backup:monitor` (cek kesehatan backup) |
| 07:00 | `notifikasi:ringkasan` + `pengingat:kirim` (email) |

Backup manual & cek:
```bash
docker exec $CID php artisan backup:run
docker exec $CID php artisan backup:list
```
Restore: unduh zip dari R2 → buka dengan `BACKUP_ARCHIVE_PASSWORD` → import file `.sql`-nya.

---

## Operasi admin umum (di aplikasi)

- **Reset 2FA user** (ganti HP): menu **User** → tombol Reset 2FA (hanya super-admin).
- **Nonaktifkan karyawan resign**: menu **Karyawan** → Nonaktifkan (menolak kalau masih pegang barang; super-admin bisa paksa dengan alasan).
- **Pengaturan per PT** (kop BAST, logo, format nomor, ukuran label): menu **Pengaturan** (super-admin).
- **Pindah aset antar PT**: tombol di halaman aset (butuh izin khusus).

---

## ⚠️ Wajib diingat (keamanan)

- **Nyalakan 2FA di semua akun admin** dan di **Coolify** (`coolify.iqbalfhz.my.id` bisa diakses dari internet). Password bocor tanpa 2FA = data karyawan terbuka.
- **Simpan di luar server** (password manager): `APP_KEY` dan `BACKUP_ARCHIVE_PASSWORD`. Hilang = data/backup tidak bisa dipulihkan.
- **Jangan commit `.env`.** Rahasia (SMTP, R2, password DB) diisi langsung di Coolify.
- Uji pulihkan backup secara berkala — backup yang belum pernah dicoba belum tentu bisa dipakai.

---

## Variabel `.env` penting

| Variabel | Guna |
|---|---|
| `APP_KEY` | kunci enkripsi (2FA, product key). **Jangan diganti di production.** |
| `SEED_PASSWORD` | password akun awal `administrator` saat seeding |
| `BACKUP_ARCHIVE_PASSWORD` | password enkripsi file backup di R2 |
| `APP_URL` | `https://asset.iqbalfhz.my.id` (dipakai untuk tautan & batas host) |

---

## Perintah artisan khusus aplikasi

| Perintah | Guna |
|---|---|
| `php artisan lockdown` | rem darurat (lihat bagian Darurat di atas) |
| `php artisan notifikasi:ringkasan` | kirim ringkasan email (otomatis 07:00) |
| `php artisan pengingat:kirim` | kirim email pengingat tanggal penting (otomatis 07:00) |
| `php artisan shield:generate --all --panel=admin` | regen izin setelah tambah resource |
