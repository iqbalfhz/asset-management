@php
    $asset = $disposal->asset;
@endphp
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>{{ $disposal->no_berita_acara }}</title>
    <style>
        @page { margin: 22mm 20mm 20mm; }
        body { font-family: "DejaVu Sans", sans-serif; font-size: 10pt; color: #26261f; line-height: 1.45; }
        .kop { width: 100%; border-bottom: 1.5pt solid #26261f; padding-bottom: 8pt; margin-bottom: 18pt; }
        .kop td { vertical-align: middle; }
        .kop img { max-height: 46pt; max-width: 120pt; }
        .kop-text { white-space: pre-line; font-size: 9pt; }
        .kop-name { font-family: "DejaVu Serif", serif; font-size: 13pt; font-weight: bold; }
        h1 { font-family: "DejaVu Serif", serif; font-size: 13pt; letter-spacing: 0.5pt; text-align: center; margin: 0; }
        .nomor { text-align: center; margin: 2pt 0 16pt; font-size: 9.5pt; }
        table.data { width: 100%; border-collapse: collapse; margin: 8pt 0 12pt; }
        table.data td { border-bottom: 0.5pt solid #c9c9bd; padding: 5pt; vertical-align: top; }
        table.data td.label { width: 34%; color: #55554b; }
        .signatures { width: 100%; margin-top: 28pt; }
        .signatures td { width: 50%; text-align: center; vertical-align: top; }
        .sign-space { height: 58pt; }
        .footer { position: fixed; bottom: -8mm; left: 0; right: 0; font-size: 7.5pt; color: #8a8a7d; }
    </style>
</head>
<body>
    @include('pdf.partials.kop')

    <h1>{{ __('BERITA ACARA PENGHAPUSAN ASET') }}</h1>
    <div class="nomor">{{ __('Nomor') }}: {{ $disposal->no_berita_acara }}</div>

    <p>{{ __('Pada tanggal :tanggal, aset berikut dihapuskan dari daftar aset :perusahaan:', ['tanggal' => $disposal->tanggal->translatedFormat('j F Y'), 'perusahaan' => $company->nama]) }}</p>

    <table class="data">
        <tr><td class="label">{{ __('Kode aset') }}</td><td>{{ $asset->kode }}</td></tr>
        <tr><td class="label">{{ __('Nama aset') }}</td><td>{{ $asset->nama }}</td></tr>
        <tr><td class="label">{{ __('Kategori') }}</td><td>{{ $asset->category->nama }}</td></tr>
        <tr><td class="label">{{ __('Nomor seri') }}</td><td>{{ $asset->no_seri ?? '—' }}</td></tr>
        <tr><td class="label">{{ __('Tanggal beli') }}</td><td>{{ $asset->tgl_beli?->translatedFormat('j F Y') ?? '—' }}</td></tr>
        <tr><td class="label">{{ __('Harga beli') }}</td><td>{{ $asset->harga_beli !== null ? 'Rp '.number_format($asset->harga_beli, 0, ',', '.') : '—' }}</td></tr>
        <tr><td class="label">{{ __('Metode penghapusan') }}</td><td>{{ $disposal->metode->getLabel() }}</td></tr>
        @if ($disposal->nilai_jual !== null)
            <tr><td class="label">{{ __('Nilai jual') }}</td><td>Rp {{ number_format($disposal->nilai_jual, 0, ',', '.') }}</td></tr>
        @endif
        <tr><td class="label">{{ __('Alasan') }}</td><td>{{ $disposal->alasan }}</td></tr>
        @if (filled($disposal->catatan))
            <tr><td class="label">{{ __('Catatan persetujuan') }}</td><td>{{ $disposal->catatan }}</td></tr>
        @endif
    </table>

    <p>{{ __('Aset ini tidak dipakai lagi. Datanya tetap disimpan untuk riwayat dan laporan.') }}</p>

    <table class="signatures">
        <tr>
            <td>{{ __('Diajukan oleh') }}</td>
            <td>{{ __('Disetujui oleh') }}</td>
        </tr>
        <tr>
            <td class="sign-space"></td>
            <td class="sign-space"></td>
        </tr>
        <tr>
            <td>( {{ $disposal->submitter->name }} )</td>
            <td>( {{ $disposal->decider->name }} )</td>
        </tr>
    </table>

    <div class="footer">{{ $disposal->no_berita_acara }} · {{ __('Dicetak dari Asset Management pada :waktu', ['waktu' => now()->translatedFormat('j F Y, H:i')]) }}</div>
</body>
</html>
