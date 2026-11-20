{{-- Footer semua panel: nama aplikasi, versi yang sedang jalan, kontak IT dari Pengaturan, dan pengembang. --}}
@php
    $commit = substr((string) config('app.commit'), 0, 7);
    $itContact = \App\Models\Setting::get('kontak_it');
@endphp

<footer class="kertas-footer">
    <p>
        {{ config('app.name') }}
        · {{ __('versi :versi', ['versi' => config('app.version')]) }}
        @if ($commit !== '')
            ({{ $commit }})
        @endif
        · © {{ now()->year }}
    </p>

    @if (filled($itContact))
        <p>{{ __('Ada kendala? Hubungi Tim IT: :kontak', ['kontak' => $itContact]) }}</p>
    @endif

    <p>
        {{ __('Dikembangkan oleh :nama', ['nama' => 'Iqbal Fahrozi']) }}
        · <a href="https://github.com/iqbalfhz" target="_blank" rel="noopener noreferrer">github.com/iqbalfhz</a>
    </p>
</footer>
