@php
    use App\Enums\AuditResult;
    use App\Enums\AuditStatus;

    $room = $audit->room;
    $location = "{$room->floor->building->nama} — {$room->floor->nama} — {$room->nama}";
@endphp
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>{{ __('Laporan stock opname') }} · {{ $room->nama }}</title>
    @include('pdf.partials.styles')
    <style>
        .empty { color: #8a8a7d; margin: 2pt 0 8pt; }
    </style>
</head>
<body>
    @include('pdf.partials.kop')

    <h1>{{ __('LAPORAN HASIL STOCK OPNAME') }}</h1>
    <div class="subtitle">
        {{ $location }}
        @if ($audit->status === AuditStatus::Berjalan)
            · {{ __('sesi masih berjalan, hasil sementara') }}
        @endif
    </div>

    <table class="data">
        <tr><td class="label">{{ __('Dimulai') }}</td><td>{{ $audit->mulai_pada->translatedFormat('j F Y, H:i') }}{{ $audit->creator ? ' · '.$audit->creator->name : '' }}</td></tr>
        <tr><td class="label">{{ __('Ditutup') }}</td><td>{{ $audit->selesai_pada?->translatedFormat('j F Y, H:i') ?? '—' }}{{ $audit->closer ? ' · '.$audit->closer->name : '' }}</td></tr>
        @foreach ($results as $result)
            <tr><td class="label">{{ $result->getLabel() }}</td><td>{{ count($items[$result->value] ?? []) }} {{ __('aset') }}</td></tr>
        @endforeach
        @if (filled($audit->catatan))
            <tr><td class="label">{{ __('Catatan') }}</td><td>{{ $audit->catatan }}</td></tr>
        @endif
    </table>

    @foreach ([AuditResult::TidakDitemukan, AuditResult::SalahTempat, AuditResult::Menunggu, AuditResult::Ditemukan] as $result)
        @continue($result === AuditResult::Menunggu && empty($items[$result->value]))

        <h2>{{ $result->getLabel() }}</h2>
        @if (empty($items[$result->value]))
            <p class="empty">{{ __('Tidak ada.') }}</p>
        @else
            <table class="list">
                <tr>
                    <th style="width: 26%;">{{ __('Kode') }}</th>
                    <th>{{ __('Nama') }}</th>
                    <th style="width: 20%;">{{ __('Kategori') }}</th>
                    @if ($result === AuditResult::SalahTempat)
                        <th style="width: 22%;">{{ __('Tercatat di') }}</th>
                    @endif
                </tr>
                @foreach ($items[$result->value] as $item)
                    <tr>
                        <td>{{ $item->asset->kode }}</td>
                        <td>{{ $item->asset->nama }}</td>
                        <td>{{ $item->asset->category->nama }}</td>
                        @if ($result === AuditResult::SalahTempat)
                            <td>{{ $item->recordedRoom?->nama ?? '—' }}{{ $item->dipindahkan_pada ? ' ('.__('sudah dipindahkan').')' : '' }}</td>
                        @endif
                    </tr>
                @endforeach
            </table>
        @endif
    @endforeach

    <table class="signatures">
        <tr>
            <td>{{ __('Petugas') }}</td>
            <td>{{ __('Mengetahui') }}</td>
        </tr>
        <tr>
            <td class="sign-space"></td>
            <td class="sign-space"></td>
        </tr>
        <tr>
            <td>( {{ $audit->creator?->name ?? '..........................' }} )</td>
            <td>( .......................... )</td>
        </tr>
    </table>

    <div class="footer">{{ __('Stock opname') }} {{ $room->nama }} · {{ __('Dicetak dari Asset Management pada :waktu', ['waktu' => now()->translatedFormat('j F Y, H:i')]) }}</div>
</body>
</html>
