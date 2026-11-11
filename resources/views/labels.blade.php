@php
    use App\Enums\LabelField;

    [$w, $h] = $size->dimensions();
    $pad = 2;
    $gap = 1.5;
    $barcodeH = $fields->contains(LabelField::Barcode) ? ($h >= 30 ? 6 : 5) : 0;
    $qr = $fields->contains(LabelField::Qr)
        ? round(min($h - 2 * $pad - ($barcodeH ? $barcodeH + $gap : 0), ($w - 2 * $pad) * 0.45), 1)
        : 0;
    // Huruf ikut membesar di label yang lebih besar.
    $scale = round(min(1.3, $w / 50, $h / 25), 2);
    $pages = $size->isSheet() ? $labels->chunk(24) : $labels->chunk(1);
@endphp
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('Label aset') }} · {{ config('app.name') }}</title>
    <style>
        @page { size: {{ $size->isSheet() ? '210mm 297mm' : "{$w}mm {$h}mm" }}; margin: 0; }
        * { box-sizing: border-box; }
        html, body { margin: 0; padding: 0; }
        body { font-family: Arial, Helvetica, sans-serif; color: #000; background: #e9e9e4; }
        .toolbar { position: sticky; top: 0; display: flex; flex-wrap: wrap; gap: 8px 16px; align-items: center; padding: 12px 16px; background: #fff; border-bottom: 1px solid #d6d6cf; font-size: 14px; }
        .toolbar strong { font-weight: 600; }
        .toolbar .hint { color: #55554b; flex-basis: 100%; font-size: 13px; }
        .toolbar button, .toolbar a { font: inherit; padding: 6px 16px; border-radius: 999px; border: 1px solid #2f6b55; text-decoration: none; cursor: pointer; }
        .toolbar button { background: #2f6b55; color: #fff; }
        .toolbar a { background: #fff; color: #2f6b55; }
        .page { background: #fff; margin: 16px auto; overflow: hidden; break-after: page; }
        .page:last-child { break-after: auto; }
        .page.sheet { width: 210mm; height: 297mm; padding-top: 0.5mm; display: grid; grid-template-columns: repeat(3, 70mm); grid-auto-rows: 37mm; }
        .page.single { width: {{ $w }}mm; height: {{ $h }}mm; }
        .label { width: {{ $w }}mm; height: {{ $h }}mm; padding: {{ $pad }}mm; display: flex; flex-direction: column; gap: {{ $gap }}mm; overflow: hidden; }
        .page.sheet .label { outline: 0.2mm dashed #ccc; outline-offset: -0.1mm; }
        .top { flex: 1; min-height: 0; display: flex; gap: {{ $gap }}mm; align-items: center; }
        .qr { width: {{ $qr }}mm; height: {{ $qr }}mm; flex: none; }
        .qr svg, .barcode svg { display: block; width: 100%; height: 100%; }
        .info { flex: 1; min-width: 0; display: flex; flex-direction: column; justify-content: center; gap: {{ 0.5 * $scale }}mm; line-height: 1.15; }
        .info div { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .company { font-size: {{ 1.9 * $scale }}mm; text-transform: uppercase; letter-spacing: 0.1mm; }
        .code { font-size: {{ 2.7 * $scale }}mm; font-weight: 700; }
        .name { font-size: {{ 2.2 * $scale }}mm; }
        .serial { font-size: {{ 1.9 * $scale }}mm; }
        .barcode { height: {{ $barcodeH }}mm; flex: none; }
        @media print {
            body { background: #fff; }
            .toolbar { display: none; }
            .page { margin: 0; }
            .page.sheet .label { outline: none; }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <strong>{{ trans_choice(':count label|:count label', $labels->count(), ['count' => $labels->count()]) }}</strong>
        <span>{{ $size->getLabel() }}</span>
        <button type="button" onclick="window.print()">{{ __('Cetak') }}</button>
        <a href="{{ \App\Filament\Resources\Assets\AssetResource::getUrl('index') }}">{{ __('Kembali ke daftar aset') }}</a>
        <span class="hint">
            {{ $size->isSheet()
                ? __('Di dialog cetak, pilih kertas A4, margin None, dan skala 100%.')
                : __('Di dialog cetak, pilih printer label, ukuran kertas :ukuran, margin None, dan skala 100%.', ['ukuran' => "{$w} × {$h} mm"]) }}
        </span>
    </div>

    @foreach ($pages as $page)
        <div class="page {{ $size->isSheet() ? 'sheet' : 'single' }}">
            @foreach ($page as $label)
                <div class="label">
                    <div class="top">
                        @if ($label['qr'])
                            <div class="qr">{!! $label['qr'] !!}</div>
                        @endif
                        <div class="info">
                            @if ($fields->contains(LabelField::Perusahaan))
                                <div class="company">{{ $label['asset']->company->nama }}</div>
                            @endif
                            <div class="code">{{ $label['asset']->kode }}</div>
                            @if ($fields->contains(LabelField::NamaAset))
                                <div class="name">{{ $label['asset']->nama }}</div>
                            @endif
                            @if ($fields->contains(LabelField::NomorSeri) && filled($label['asset']->no_seri))
                                <div class="serial">SN {{ $label['asset']->no_seri }}</div>
                            @endif
                        </div>
                    </div>
                    @if ($label['barcode'])
                        <div class="barcode">{!! $label['barcode'] !!}</div>
                    @endif
                </div>
            @endforeach
        </div>
    @endforeach
</body>
</html>
