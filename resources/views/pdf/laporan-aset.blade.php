@php
    use Illuminate\Support\Number;

    $rupiah = fn (?int $value): string => $value === null ? '—' : Number::currency($value, in: 'IDR', locale: 'id', precision: 0);
@endphp
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>{{ __('Laporan aset') }} · {{ $company->nama }}</title>
    @include('pdf.partials.styles')
    <style>
        table.list .num { text-align: right; }
        .notes { margin-top: 16pt; font-size: 8.5pt; color: #55554b; }
        .notes p { margin: 0 0 3pt; }
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
