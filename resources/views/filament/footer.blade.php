{{-- Footer semua panel: nama aplikasi, versi yang sedang jalan, kontak IT dari Pengaturan, dan pengembang. --}}
@php
    $version = substr((string) config('app.version'), 0, 7);
    $itContact = \App\Models\Setting::get('kontak_it');
@endphp

<footer class="kertas-footer">
    <p>
        {{ config('app.name') }}
        @if ($version !== '')
            · {{ __('versi :versi', ['versi' => $version]) }}
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
