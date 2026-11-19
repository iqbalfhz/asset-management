@php
    use App\Enums\ReceiptMethod;

    $isReturn = $first->jenis === \App\Enums\AssignmentType::Kembali;
    $admin = $first->createdBy->name.', '.__('atas nama').' '.$company->nama;
    $target = $first->targetLabel();
    $giver = $isReturn ? $target : $admin;
    $receiver = $isReturn ? $admin : $target;
    $signature = $isReturn ? null : $first->signatureDataUri();
    $receipt = $isReturn ? null : $first->receiptDataUri();
    $receivedBy = ['waktu' => $first->accepted_at?->translatedFormat('j F Y, H:i'), 'nama' => $first->confirmedBy?->name ?? '—'];
    // Belum diterima: tanpa catatan, kotak tanda tangan dibiarkan kosong untuk ditandatangani di BAST cetak.
    $receiptNote = $isReturn ? null : match ($first->receipt_method) {
        ReceiptMethod::Aplikasi => __('Diterima oleh Pihak Kedua melalui aplikasi pada :waktu.', $receivedBy),
        ReceiptMethod::Layar => __('Ditandatangani Pihak Kedua di hadapan :nama pada :waktu.', $receivedBy),
        ReceiptMethod::Kertas => __('Ditandatangani Pihak Kedua di BAST cetak. Fotonya diunggah oleh :nama pada :waktu dan terlampir di halaman berikut.', $receivedBy),
        null => null,
    };
@endphp
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>{{ $first->no_bast }}</title>
    @include('pdf.partials.styles')
    <style>
        .parties td { padding: 2pt 0; vertical-align: top; }
        .parties .label { width: 34%; color: #55554b; }
        table.items { width: 100%; border-collapse: collapse; margin: 8pt 0 12pt; }
        table.items th { text-align: left; font-size: 8.5pt; text-transform: uppercase; letter-spacing: 0.4pt; color: #55554b; border-bottom: 1pt solid #26261f; padding: 4pt 5pt; }
        table.items td { border-bottom: 0.5pt solid #c9c9bd; padding: 5pt; vertical-align: top; }
        .note { margin: 6pt 0; }
        .receipt { page-break-before: always; text-align: center; }
        .receipt img { max-width: 170mm; max-height: 215mm; }
    </style>
</head>
<body>
    @include('pdf.partials.kop')

    <h1>{{ $isReturn ? __('BERITA ACARA PENGEMBALIAN ASET') : __('BERITA ACARA SERAH TERIMA ASET') }}</h1>
    <div class="subtitle">{{ __('Nomor') }}: {{ $first->no_bast }}</div>

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

    @if ($receiptNote)
        <p class="note muted">{{ $receiptNote }}</p>
    @endif

    <p>{{ __('Demikian berita acara ini dibuat untuk dipergunakan sebagaimana mestinya.') }}</p>

    <table class="signatures">
        <tr>
            <td>{{ __('Pihak Pertama') }}</td>
            <td>{{ __('Pihak Kedua') }}</td>
        </tr>
        <tr>
            <td class="sign-space"></td>
            <td class="sign-space">@if ($signature)<img src="{{ $signature }}" alt="">@endif</td>
        </tr>
        <tr>
            <td>( {{ $isReturn ? $target : $first->createdBy->name }} )</td>
            <td>( {{ $isReturn ? $first->createdBy->name : $target }} )</td>
        </tr>
    </table>

    @if ($receipt)
        <div class="receipt">
            <h1>{{ __('LAMPIRAN: FOTO BAST YANG DITANDATANGANI') }}</h1>
            <div class="subtitle">{{ __('Nomor') }}: {{ $first->no_bast }}</div>
            <img src="{{ $receipt }}" alt="">
        </div>
    @endif

    <div class="footer">{{ $first->no_bast }} · {{ __('Dicetak dari Asset Management pada :waktu', ['waktu' => now()->translatedFormat('j F Y, H:i')]) }}</div>
</body>
</html>
