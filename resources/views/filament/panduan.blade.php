<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('Panduan karyawan') }} · {{ config('app.name') }}</title>
    @vite('resources/css/filament/admin/theme.css')
    <style>
        :root {
            --ground: oklch(0.956 0.009 105);
            --paper: #fff;
            --ink: oklch(0.225 0.008 110);
            --quiet: oklch(0.455 0.012 110);
            --rule: oklch(0.905 0.008 110);
            --pine: oklch(0.40 0.07 165);
            --pine-soft: oklch(0.94 0.03 165);
        }
        body { margin: 0; background: var(--ground); color: var(--ink); font-family: 'Plus Jakarta Sans Variable', ui-sans-serif, system-ui, sans-serif; font-size: 1rem; line-height: 1.6; }
        .wrap { max-width: 44rem; margin: 0 auto; padding: 3rem 1rem 4rem; }
        h1, h2 { font-family: 'Newsreader Variable', Georgia, serif; font-optical-sizing: auto; font-weight: 600; letter-spacing: -0.01em; margin: 0; }
        h1 { font-size: 2.25rem; line-height: 1.15; }
        .lead { color: var(--quiet); margin: 0.75rem 0 2.5rem; max-width: 36rem; }
        .step { display: grid; grid-template-columns: 3rem 1fr; gap: 0 1rem; padding: 1.75rem 0; border-top: 1px solid var(--rule); }
        .num { font-family: 'Newsreader Variable', Georgia, serif; font-size: 2.5rem; line-height: 1; color: var(--pine); }
        h2 { font-size: 1.4rem; line-height: 1.25; margin-bottom: 0.75rem; }
        ol { margin: 0; padding-left: 1.2rem; }
        li { margin: 0.35rem 0; }
        .note { margin-top: 1rem; padding: 0.75rem 1rem; background: var(--pine-soft); border-radius: 0.625rem; font-size: 0.95rem; }
        strong { font-weight: 600; }
        a { color: var(--pine); font-weight: 600; text-underline-offset: 2px; }
        .foot { border-top: 1px solid var(--rule); padding-top: 1.5rem; color: var(--quiet); font-size: 0.95rem; }
        .cta { display: inline-block; margin-top: 0.75rem; padding: 0.6rem 1.25rem; border-radius: 999px; background: var(--pine); color: #fff; text-decoration: none; }
        @media (max-width: 30rem) { .step { grid-template-columns: 1fr; } .num { margin-bottom: 0.5rem; } h1 { font-size: 1.85rem; } }
    </style>
</head>
<body>
    <main class="wrap">
        <h1>{{ __('Panduan karyawan') }}</h1>
        <p class="lead">{{ __('Tiga hal yang perlu Anda lakukan di :app: masuk pertama kali, menerima aset, dan melapor kerusakan.', ['app' => config('app.name')]) }}</p>

        <section class="step">
            <div class="num">1</div>
            <div>
                <h2>{{ __('Masuk pertama kali dan memasang kode keamanan') }}</h2>
                <ol>
                    <li>{{ __('Pasang aplikasi authenticator di HP Anda, misalnya Google Authenticator atau Microsoft Authenticator, dari Play Store atau App Store.') }}</li>
                    <li>{!! __('Buka :link, lalu masuk dengan username atau email dan password dari admin.', ['link' => '<a href="'.e(url('/karyawan')).'">'.e(__('panel karyawan')).'</a>']) !!}</li>
                    <li>{{ __('Aplikasi meminta Anda mengaktifkan autentikasi dua faktor. Pindai kode QR yang muncul dengan aplikasi authenticator, lalu ketik 6 angka yang tampil di HP.') }}</li>
                    <li>{{ __('Simpan kode pemulihan yang ditampilkan di tempat aman. Kode ini dipakai kalau HP Anda hilang atau diganti.') }}</li>
                    <li>{{ __('Setiap kali masuk berikutnya, ketik 6 angka terbaru dari aplikasi authenticator setelah memasukkan password.') }}</li>
                </ol>
                <p class="note">{{ __('Kode di aplikasi authenticator berganti setiap 30 detik. Kalau ditolak, tunggu angka berikutnya lalu coba lagi.') }}</p>
            </div>
        </section>

        <section class="step">
            <div class="num">2</div>
            <div>
                <h2>{{ __('Menerima aset dari admin') }}</h2>
                <ol>
                    <li>{!! __('Saat admin menyerahkan aset, menu :menu menampilkan angka di sampingnya.', ['menu' => '<strong>'.e(__('Aset saya')).'</strong>']) !!}</li>
                    <li>{{ __('Buka Aset saya dan cocokkan kode serta nomor seri dengan barang yang Anda terima.') }}</li>
                    <li>{!! __('Tekan :tombol, baca syarat penggunaannya, centang persetujuan, lalu tekan Terima lagi.', ['tombol' => '<strong>'.e(__('Terima')).'</strong>']) !!}</li>
                    <li>{!! __('Berita acara (BAST) bisa diunduh kapan saja di menu :menu.', ['menu' => '<strong>'.e(__('Riwayat')).'</strong>']) !!}</li>
                </ol>
                <p class="note">{{ __('Kalau barangnya tidak sesuai, jangan tekan Terima. Hubungi admin yang menyerahkan.') }}</p>
            </div>
        </section>

        <section class="step">
            <div class="num">3</div>
            <div>
                <h2>{{ __('Melapor kerusakan') }}</h2>
                <ol>
                    <li>{!! __('Di Aset saya, tekan :tombol pada aset yang bermasalah.', ['tombol' => '<strong>'.e(__('Lapor kerusakan')).'</strong>']) !!}</li>
                    <li>{{ __('Tulis masalahnya sejelas mungkin. Tambahkan foto kalau membantu.') }}</li>
                    <li>{{ __('Pantau statusnya di menu Lapor kerusakan: Dilaporkan, lalu Diproses, lalu Selesai. Kalau ditolak, alasannya tertulis di catatan admin.') }}</li>
                </ol>
            </div>
        </section>

        <footer class="foot">
            {{ __('Butuh bantuan? Hubungi tim IT kantor.') }}<br>
            <a class="cta" href="{{ url('/karyawan') }}">{{ __('Masuk ke panel karyawan') }}</a>
        </footer>
    </main>
</body>
</html>
