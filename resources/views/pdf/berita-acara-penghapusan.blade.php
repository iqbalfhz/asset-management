@php
    use Illuminate\Support\Number;

    $asset = $disposal->asset;
@endphp
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>{{ $disposal->no_berita_acara }}</title>
    @include('pdf.partials.styles')
</head>
<body>
    @include('pdf.partials.kop')

    <h1>{{ __('BERITA ACARA PENGHAPUSAN ASET') }}</h1>
    <div class="subtitle">{{ __('Nomor') }}: {{ $disposal->no_berita_acara }}</div>

    <p>{{ __('Pada tanggal :tanggal, aset berikut dihapuskan dari daftar aset :perusahaan:', ['tanggal' => $disposal->tanggal->translatedFormat('j F Y'), 'perusahaan' => $company->nama]) }}</p>

    <table class="data">
        <tr><td class="label">{{ __('Kode aset') }}</td><td>{{ $asset->kode }}</td></tr>
        <tr><td class="label">{{ __('Nama aset') }}</td><td>{{ $asset->nama }}</td></tr>
        <tr><td class="label">{{ __('Kategori') }}</td><td>{{ $asset->category->nama }}</td></tr>
        <tr><td class="label">{{ __('Nomor seri') }}</td><td>{{ $asset->no_seri ?? '—' }}</td></tr>
        <tr><td class="label">{{ __('Tanggal beli') }}</td><td>{{ $asset->tgl_beli?->translatedFormat('j F Y') ?? '—' }}</td></tr>
        <tr><td class="label">{{ __('Harga beli') }}</td><td>{{ $asset->harga_beli !== null ? Number::currency($asset->harga_beli, in: 'IDR', locale: 'id', precision: 0) : '—' }}</td></tr>
        <tr><td class="label">{{ __('Metode penghapusan') }}</td><td>{{ $disposal->metode->getLabel() }}</td></tr>
        @if ($disposal->nilai_jual !== null)
            <tr><td class="label">{{ __('Nilai jual') }}</td><td>{{ Number::currency($disposal->nilai_jual, in: 'IDR', locale: 'id', precision: 0) }}</td></tr>
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
