@php
    $isReturn = $first->jenis === \App\Enums\AssignmentType::Kembali;
    $admin = $first->createdBy->name.', '.__('atas nama').' '.$company->nama;
    $target = $first->targetLabel();
    $giver = $isReturn ? $target : $admin;
    $receiver = $isReturn ? $admin : $target;
@endphp
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>{{ $first->no_bast }}</title>
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
        .parties td { padding: 2pt 0; vertical-align: top; }
        .parties .label { width: 34%; color: #55554b; }
        table.items { width: 100%; border-collapse: collapse; margin: 8pt 0 12pt; }
        table.items th { text-align: left; font-size: 8.5pt; text-transform: uppercase; letter-spacing: 0.4pt; color: #55554b; border-bottom: 1pt solid #26261f; padding: 4pt 5pt; }
        table.items td { border-bottom: 0.5pt solid #c9c9bd; padding: 5pt; vertical-align: top; }
        .muted { color: #55554b; }
        .note { margin: 6pt 0; }
        .signatures { width: 100%; margin-top: 28pt; }
        .signatures td { width: 50%; text-align: center; vertical-align: top; }
        .sign-space { height: 58pt; }
        .footer { position: fixed; bottom: -8mm; left: 0; right: 0; font-size: 7.5pt; color: #8a8a7d; }
    </style>
</head>
<body>
    <table class="kop">
        <tr>
            @if ($logo)
                <td style="width: 130pt;"><img src="{{ $logo }}" alt=""></td>
            @endif
            <td>
                @if (filled($kop))
                    <div class="kop-text">{{ $kop }}</div>
                @else
                    <div class="kop-name">{{ $company->nama }}</div>
                @endif
            </td>
        </tr>
    </table>

    <h1>{{ $isReturn ? __('BERITA ACARA PENGEMBALIAN ASET') : __('BERITA ACARA SERAH TERIMA ASET') }}</h1>
    <div class="nomor">{{ __('Nomor') }}: {{ $first->no_bast }}</div>

    <p>{{ __('Pada tanggal :tanggal, yang bertanda tangan di bawah ini:', ['tanggal' => $first->tanggal->translatedFormat('j F Y')]) }}</p>

    <table class="parties">
        <tr>
            <td class="label">{{ $isReturn ? __('Pihak Pertama (yang mengembalikan)') : __('Pihak Pertama (yang menyerahkan)') }}</td>
            <td>{{ $giver }}</td>
        </tr>
        <tr>
            <td class="label">{{ __('Pihak Kedua (yang menerima)') }}</td>
            <td>{{ $receiver }}</td>
        </tr>
    </table>

    <p>{{ $isReturn ? __('Pihak Pertama mengembalikan kepada Pihak Kedua aset berikut:') : __('Pihak Pertama menyerahkan kepada Pihak Kedua aset berikut:') }}</p>

    <table class="items">
        <thead>
            <tr>
                <th style="width: 6%;">{{ __('No') }}</th>
                <th style="width: 24%;">{{ __('Kode') }}</th>
                <th>{{ __('Nama aset') }}</th>
                <th style="width: 20%;">{{ __('Nomor seri') }}</th>
                <th style="width: 18%;">{{ __('Kondisi') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($rows as $row)
                <tr>
                    <td>{{ $loop->iteration }}</td>
                    <td>{{ $row->asset->kode }}</td>
                    <td>{{ $row->asset->nama }}</td>
                    <td>{{ $row->asset->no_seri ?? '—' }}</td>
                    <td>{{ $row->kondisi }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    @if ($first->expected_return_at)
        <p class="note">{{ __('Aset ini dipinjamkan dan dikembalikan paling lambat :tanggal.', ['tanggal' => $first->expected_return_at->translatedFormat('j F Y')]) }}</p>
    @endif

    @if (filled($first->catatan))
        <p class="note"><span class="muted">{{ __('Catatan') }}:</span> {{ $first->catatan }}</p>
    @endif

    @if (! $isReturn && $first->target_type === (new \App\Models\Employee)->getMorphClass())
        <p class="note muted">
            @if ($first->accepted_at)
                {{ __('Diterima oleh Pihak Kedua melalui aplikasi pada :waktu.', ['waktu' => $first->accepted_at->translatedFormat('j F Y, H:i')]) }}
            @else
                {{ __('Menunggu konfirmasi penerimaan dari Pihak Kedua di aplikasi.') }}
            @endif
        </p>
    @endif

    <p>{{ __('Demikian berita acara ini dibuat untuk dipergunakan sebagaimana mestinya.') }}</p>

    <table class="signatures">
        <tr>
            <td>{{ __('Pihak Pertama') }}</td>
            <td>{{ __('Pihak Kedua') }}</td>
        </tr>
        <tr>
            <td class="sign-space"></td>
            <td class="sign-space"></td>
        </tr>
        <tr>
            <td>( {{ $isReturn ? $target : $first->createdBy->name }} )</td>
            <td>( {{ $isReturn ? $first->createdBy->name : $target }} )</td>
        </tr>
    </table>

    <div class="footer">{{ $first->no_bast }} · {{ __('Dicetak dari Asset Management pada :waktu', ['waktu' => now()->translatedFormat('j F Y, H:i')]) }}</div>
</body>
</html>
