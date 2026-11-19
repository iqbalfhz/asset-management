@php
    $rupiah = fn (?int $value): string => $value === null ? '—' : 'Rp '.number_format($value, 0, ',', '.');
@endphp
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>{{ __('Laporan aset') }} · {{ $company->nama }}</title>
    <style>
        @page { margin: 22mm 20mm 20mm; }
        body { font-family: "DejaVu Sans", sans-serif; font-size: 9.5pt; color: #26261f; line-height: 1.45; }
        .kop { width: 100%; border-bottom: 1.5pt solid #26261f; padding-bottom: 8pt; margin-bottom: 18pt; }
        .kop td { vertical-align: middle; }
        .kop img { max-height: 46pt; max-width: 120pt; }
        .kop-text { white-space: pre-line; font-size: 9pt; }
        .kop-name { font-family: "DejaVu Serif", serif; font-size: 13pt; font-weight: bold; }
        h1 { font-family: "DejaVu Serif", serif; font-size: 13pt; letter-spacing: 0.5pt; text-align: center; margin: 0; }
        .subtitle { text-align: center; margin: 2pt 0 16pt; font-size: 9pt; }
        h2 { font-family: "DejaVu Serif", serif; font-size: 11pt; margin: 16pt 0 4pt; }
        table.data { width: 100%; border-collapse: collapse; margin: 4pt 0 10pt; }
        table.data td { border-bottom: 0.5pt solid #c9c9bd; padding: 4pt 5pt; vertical-align: top; }
        table.data td.label { width: 30%; color: #55554b; }
        table.list { width: 100%; border-collapse: collapse; margin: 4pt 0 8pt; }
        table.list th { text-align: left; font-weight: bold; font-size: 8.5pt; color: #55554b; border-bottom: 0.75pt solid #26261f; padding: 3pt 5pt; }
        table.list td { border-bottom: 0.5pt solid #c9c9bd; padding: 3pt 5pt; vertical-align: top; }
        table.list .num { text-align: right; }
        .notes { margin-top: 16pt; font-size: 8.5pt; color: #55554b; }
        .notes p { margin: 0 0 3pt; }
        .footer { position: fixed; bottom: -8mm; left: 0; right: 0; font-size: 7.5pt; color: #8a8a7d; }
    </style>
</head>
<body>
    @include('pdf.partials.kop')

    <h1>{{ __('LAPORAN ASET') }}</h1>
    <div class="subtitle">
        {{ $company->nama }}{{ $department ? ' · '.$department->nama : '' }} · {{ __('nilai buku per :tanggal', ['tanggal' => $date->translatedFormat('j F Y')]) }}
    </div>

    <table class="data">
        <tr><td class="label">{{ __('Jumlah aset') }}</td><td>{{ $total['jumlah'] }}</td></tr>
        <tr><td class="label">{{ __('Total harga beli') }}</td><td>{{ $rupiah($total['harga']) }}</td></tr>
        <tr><td class="label">{{ __('Total nilai buku') }}</td><td>{{ $rupiah($total['nilai']) }}</td></tr>
    </table>

    @foreach ($sections as $section)
        <h2>{{ $section['title'] }}</h2>
        <table class="list">
            <tr>
                <th>{{ $section['column'] }}</th>
                <th class="num" style="width: 12%;">{{ __('Jumlah') }}</th>
                <th class="num" style="width: 22%;">{{ __('Harga beli') }}</th>
                <th class="num" style="width: 22%;">{{ __('Nilai buku') }}</th>
            </tr>
            @forelse ($section['groups'] as $name => $summary)
                <tr>
                    <td>{{ $name !== '' ? $name : __('Tidak dipegang karyawan atau departemen') }}</td>
                    <td class="num">{{ $summary['jumlah'] }}</td>
                    <td class="num">{{ $rupiah($summary['harga']) }}</td>
                    <td class="num">{{ $rupiah($summary['nilai']) }}</td>
                </tr>
            @empty
                <tr><td colspan="4">{{ __('Tidak ada.') }}</td></tr>
            @endforelse
        </table>
    @endforeach

    <div class="notes">
        <p>{{ __('Tidak termasuk aset yang sudah dihapuskan dan perangkat pribadi (BYOD). Jumlah, status, dan pemegang sesuai data saat dicetak.') }}</p>
        @if ($withoutValue > 0)
            <p>{{ __(':jumlah aset belum punya harga beli, tanggal beli, atau kelas penyusutan, jadi tidak ikut nilai buku.', ['jumlah' => $withoutValue]) }}</p>
        @endif
        @if ($restricted)
            <p>{{ __('Hanya berisi aset dari kategori yang dikelola :nama.', ['nama' => auth()->user()->name]) }}</p>
        @endif
    </div>

    <div class="footer">{{ __('Laporan aset') }} {{ $company->nama }} · {{ __('Dicetak dari Asset Management pada :waktu', ['waktu' => now()->translatedFormat('j F Y, H:i')]) }}</div>
</body>
</html>
