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
